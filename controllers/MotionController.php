<?php

namespace app\controllers;

use app\components\Tools;
use app\components\UrlHelper;
use app\components\EmailNotifications;
use app\models\db\Amendment;
use app\models\db\ConsultationAgendaItem;
use app\models\db\ConsultationLog;
use app\models\db\ConsultationMotionType;
use app\models\db\ConsultationSettingsMotionSection;
use app\models\db\IMotion;
use app\models\db\Motion;
use app\models\db\MotionAdminComment;
use app\models\db\MotionSupporter;
use app\models\db\User;
use app\models\db\UserNotification;
use app\models\db\VotingBlock;
use app\models\exceptions\ExceptionBase;
use app\models\exceptions\FormError;
use app\models\exceptions\Inconsistency;
use app\models\exceptions\Internal;
use app\models\exceptions\MailNotSent;
use app\models\forms\MotionEditForm;
use app\models\sectionTypes\ISectionType;
use app\models\MotionSectionChanges;
use app\models\notifications\MotionProposedProcedure;
use app\models\events\MotionEvent;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class MotionController extends Base
{
    use MotionActionsTrait;
    use MotionMergingTrait;

    /**
     * @param string $motionSlug
     * @param int $sectionId
     * @return string
     * @throws \yii\base\ExitException
     */
    public function actionViewimage($motionSlug, $sectionId)
    {
        $motion = $this->getMotionWithCheck($motionSlug);

        foreach ($motion->getActiveSections() as $section) {
            if ($section->sectionId == $sectionId) {
                $metadata = json_decode($section->metadata, true);
                \yii::$app->response->format = Response::FORMAT_RAW;
                \yii::$app->response->headers->add('Content-Type', $metadata['mime']);
                if (!$this->layoutParams->isRobotsIndex($this->action)) {
                    \yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
                }
                return base64_decode($section->data);
            }
        }
        return '';
    }

    /**
     * @param string $motionSlug
     * @param int $sectionId
     * @return string
     * @throws \yii\base\ExitException
     */
    public function actionViewpdf($motionSlug, $sectionId)
    {
        $motion = $this->getMotionWithCheck($motionSlug);

        if (!$motion->isReadable() && !User::havePrivilege($this->consultation, User::PRIVILEGE_SCREENING)) {
            return $this->render('view_not_visible', ['motion' => $motion, 'adminEdit' => false]);
        }

        foreach ($motion->getActiveSections() as $section) {
            if ($section->sectionId == $sectionId) {
                \yii::$app->response->format = Response::FORMAT_RAW;
                \yii::$app->response->headers->add('Content-Type', 'application/pdf');
                if (!$this->layoutParams->isRobotsIndex($this->action)) {
                    \yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
                }
                return base64_decode($section->data);
            }
        }
        return '';
    }

    /**
     * @return string
     */
    public function actionEmbeddedpdf()
    {
        return $this->renderPartial('pdf_embed', []);
    }

    /**
     * @param string $motionSlug
     * @return Motion|null
     * @throws \yii\base\ExitException
     */
    private function getMotionWithCheck($motionSlug)
    {
        if (is_numeric($motionSlug) && $motionSlug > 0) {
            $motion = Motion::findOne([
                'consultationId' => $this->consultation->id,
                'id'             => $motionSlug,
                'slug'           => null
            ]);
        } else {
            $motion = Motion::findOne([
                'consultationId' => $this->consultation->id,
                'slug'           => $motionSlug
            ]);
        }
        /** @var Motion $motion */
        if (!$motion) {
            $redirect = $this->guessRedirectByPrefix($motionSlug);
            if ($redirect) {
                $this->redirect($redirect);
            } else {
                \Yii::$app->session->setFlash('error', \Yii::t('motion', 'err_not_found'));
                $this->redirect(UrlHelper::createUrl('consultation/index'));
            }
            \Yii::$app->end();
            return null;
        }

        $this->checkConsistency($motion);

        return $motion;
    }

    /**
     * @param string $motionSlug
     * @return string
     * @throws \yii\base\ExitException
     */
    public function actionPdf($motionSlug)
    {
        $motion = $this->getMotionWithCheck($motionSlug);

        if (!$motion->isReadable() && !User::havePrivilege($this->consultation, User::PRIVILEGE_SCREENING)) {
            return $this->render('view_not_visible', ['motion' => $motion, 'adminEdit' => false]);
        }

        $filename                    = $motion->getFilenameBase(false) . '.pdf';
        \yii::$app->response->format = Response::FORMAT_RAW;
        \yii::$app->response->headers->add('Content-Type', 'application/pdf');
        \yii::$app->response->headers->add('Content-disposition', 'filename="' . addslashes($filename) . '"');
        if (!$this->layoutParams->isRobotsIndex($this->action)) {
            \yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($this->getParams()->xelatexPath && $motion->getMyMotionType()->texTemplateId) {
            return $this->renderPartial('pdf_tex', ['motion' => $motion]);
        } else {
            return $this->renderPartial('pdf_tcpdf', ['motion' => $motion]);
        }
    }

    /**
     * @param string $motionSlug
     * @return string
     * @throws \yii\base\ExitException
     */
    public function actionPdfamendcollection($motionSlug)
    {
        $motion = $this->getMotionWithCheck($motionSlug);

        if (!$motion->isReadable() && !User::havePrivilege($this->consultation, User::PRIVILEGE_SCREENING)) {
            return $this->render('view_not_visible', ['motion' => $motion, 'adminEdit' => false]);
        }

        $amendments = $motion->getVisibleAmendmentsSorted();

        $filename                    = $motion->getFilenameBase(false) . '.collection.pdf';
        \yii::$app->response->format = Response::FORMAT_RAW;
        \yii::$app->response->headers->add('Content-Type', 'application/pdf');
        \yii::$app->response->headers->add('Content-disposition', 'filename="' . addslashes($filename) . '"');
        if (!$this->layoutParams->isRobotsIndex($this->action)) {
            \yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($this->getParams()->xelatexPath && $motion->getMyMotionType()->texTemplateId) {
            return $this->renderPartial('pdf_amend_collection_tex', [
                'motion' => $motion, 'amendments' => $amendments, 'texTemplate' => $motion->motionType->texTemplate
            ]);
        } else {
            return $this->renderPartial('pdf_amend_collection_tcpdf', [
                'motion' => $motion, 'amendments' => $amendments
            ]);
        }
    }

    /**
     * @param string $motionTypeId
     * @param int $withdrawn
     * @return string
     * @throws \yii\base\ExitException
     */
    public function actionPdfcollection($motionTypeId = '', $withdrawn = 0)
    {
        $withdrawn   = ($withdrawn == 1);
        $texTemplate = null;
        try {
            $motions = $this->consultation->getVisibleMotionsSorted($withdrawn);
            if ($motionTypeId != '' && $motionTypeId != '0') {
                $motionTypeIds = explode(',', $motionTypeId);
                $motions       = array_filter($motions, function (Motion $motion) use ($motionTypeIds) {
                    return in_array($motion->motionTypeId, $motionTypeIds);
                });
            }

            $motionsFiltered = [];
            foreach ($motions as $motion) {
                if ($texTemplate === null) {
                    $texTemplate       = $motion->motionType->texTemplate;
                    $motionsFiltered[] = $motion;
                } elseif ($motion->motionType->texTemplate == $texTemplate) {
                    $motionsFiltered[] = $motion;
                }
            }
            $motions = $motionsFiltered;

            if (count($motions) == 0) {
                return $this->showErrorpage(404, \Yii::t('motion', 'none_yet'));
            }
        } catch (ExceptionBase $e) {
            return $this->showErrorpage(404, $e->getMessage());
        }

        \yii::$app->response->format = Response::FORMAT_RAW;
        \yii::$app->response->headers->add('Content-Type', 'application/pdf');
        if (!$this->layoutParams->isRobotsIndex($this->action)) {
            \yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($this->getParams()->xelatexPath && $texTemplate) {
            return $this->renderPartial('pdf_collection_tex', ['motions' => $motions, 'texTemplate' => $texTemplate]);
        } else {
            return $this->renderPartial('pdf_collection_tcpdf', ['motions' => $motions]);
        }
    }

    /**
     * @param string $motionSlug
     * @return string
     * @throws \yii\base\ExitException
     */
    public function actionOdt($motionSlug)
    {
        $motion = $this->getMotionWithCheck($motionSlug);

        if (!$motion->isReadable() && !User::havePrivilege($this->consultation, User::PRIVILEGE_SCREENING)) {
            return $this->render('view_not_visible', ['motion' => $motion, 'adminEdit' => false]);
        }

        $filename                    = $motion->getFilenameBase(false) . '.odt';
        \yii::$app->response->format = Response::FORMAT_RAW;
        \yii::$app->response->headers->add('Content-Type', 'application/vnd.oasis.opendocument.text');
        \yii::$app->response->headers->add('Content-disposition', 'filename="' . addslashes($filename) . '"');
        if (!$this->layoutParams->isRobotsIndex($this->action)) {
            \yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $this->renderPartial('view_odt', ['motion' => $motion]);
    }


    /**
     * @param string $motionSlug
     * @return string
     * @throws \yii\base\ExitException
     */
    public function actionPlainhtml($motionSlug)
    {
        $motion = $this->getMotionWithCheck($motionSlug);

        if (!$motion->isReadable() && !User::havePrivilege($this->consultation, User::PRIVILEGE_SCREENING)) {
            return $this->render('view_not_visible', ['motion' => $motion, 'adminEdit' => false]);
        }
        if (!$this->layoutParams->isRobotsIndex($this->action)) {
            \yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $this->renderPartial('plain_html', ['motion' => $motion]);
    }

    /**
     * @param string $motionSlug
     * @param int $commentId
     * @return string
     * @throws Internal
     * @throws \yii\base\ExitException
     */
    public function actionView($motionSlug, $commentId = 0)
    {
        $this->layout = 'column2';

        $motion = $this->getMotionWithCheck($motionSlug);
        if (User::havePrivilege($this->consultation, User::PRIVILEGE_SCREENING)) {
            $adminEdit = UrlHelper::createUrl(['admin/motion/update', 'motionId' => $motion->id]);
        } else {
            $adminEdit = null;
        }

        if (!$motion->isReadable()) {
            return $this->render('view_not_visible', ['motion' => $motion, 'adminEdit' => $adminEdit]);
        }

        $openedComments = [];
        if ($commentId > 0) {
            foreach ($motion->getActiveSections(ISectionType::TYPE_TEXT_SIMPLE) as $section) {
                foreach ($section->getTextParagraphObjects(false, true, true) as $paragraph) {
                    foreach ($paragraph->comments as $comment) {
                        if ($comment->id == $commentId) {
                            if (!isset($openedComments[$section->sectionId])) {
                                $openedComments[$section->sectionId] = [];
                            }
                            $openedComments[$section->sectionId][] = $paragraph->paragraphNo;
                        }
                    }
                }
            }
        }


        $commentWholeMotions = false;
        foreach ($motion->getActiveSections() as $section) {
            if ($section->getSettings()->hasComments == ConsultationSettingsMotionSection::COMMENTS_MOTION) {
                $commentWholeMotions = true;
            }
        }

        $motionViewParams = [
            'motion'              => $motion,
            'openedComments'      => $openedComments,
            'adminEdit'           => $adminEdit,
            'commentForm'         => null,
            'commentWholeMotions' => $commentWholeMotions,
        ];

        try {
            $this->performShowActions($motion, $commentId, $motionViewParams);
        } catch (\Exception $e) {
            \yii::$app->session->setFlash('error', $e->getMessage());
        }

        $supportStatus = '';
        if (!\Yii::$app->user->isGuest) {
            foreach ($motion->motionSupporters as $supp) {
                if ($supp->userId == User::getCurrentUser()->id) {
                    $supportStatus = $supp->role;
                }
            }
        }
        $motionViewParams['supportStatus'] = $supportStatus;


        return $this->render('view', $motionViewParams);
    }

    /**
     * @param string $motionSlug
     * @return string
     * @throws \yii\base\ExitException
     */
    public function actionViewChanges($motionSlug)
    {
        $this->layout = 'column2';

        $motion       = $this->getMotionWithCheck($motionSlug);
        $parentMotion = $motion->replacedMotion;

        if (!$motion->isReadable()) {
            return $this->render('view_not_visible', ['motion' => $motion, 'adminEdit' => false]);
        }
        if (!$parentMotion || !$parentMotion->isReadable()) {
            \Yii::$app->session->setFlash('error', 'The diff-view is not available');
            return $this->redirect(UrlHelper::createMotionUrl($motion));
        }

        try {
            $changes = MotionSectionChanges::motionToSectionChanges($parentMotion, $motion);
        } catch (Inconsistency $e) {
            $changes = [];
            \Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->render('view_changes', [
            'newMotion' => $motion,
            'oldMotion' => $parentMotion,
            'changes'   => $changes,
        ]);
    }

    /**
     * @param string $motionSlug
     * @return string
     * @throws \yii\base\ExitException
     */
    public function actionViewChangesOdt($motionSlug)
    {
        $motion       = $this->getMotionWithCheck($motionSlug);
        $parentMotion = $motion->replacedMotion;
        $iAmAdmin     = User::havePrivilege($this->consultation, User::PRIVILEGE_CONTENT_EDIT);

        if (!$motion->isReadable() && !($motion->status == Motion::STATUS_DRAFT && $iAmAdmin)) {
            return $this->render('view_not_visible', ['motion' => $motion, 'adminEdit' => false]);
        }
        if (!$parentMotion || !$parentMotion->isReadable()) {
            \Yii::$app->session->setFlash('error', 'The diff-view is not available');
            return $this->redirect(UrlHelper::createMotionUrl($motion));
        }

        $filename                    = $motion->getFilenameBase(false) . '-changes.odt';
        \yii::$app->response->format = Response::FORMAT_RAW;
        \yii::$app->response->headers->add('Content-Type', 'application/vnd.oasis.opendocument.text');
        \yii::$app->response->headers->add('Content-disposition', 'filename="' . addslashes($filename) . '"');
        if (!$this->layoutParams->isRobotsIndex($this->action)) {
            \yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        try {
            $changes = MotionSectionChanges::motionToSectionChanges($parentMotion, $motion);
        } catch (\Exception $e) {
            return $this->showErrorpage(500, $e->getMessage());
        }

        return $this->renderPartial('view_changes_odt', [
            'newMotion' => $motion,
            'oldMotion' => $parentMotion,
            'changes'   => $changes,
        ]);
    }

    /**
     * @param string $motionSlug
     * @param string $fromMode
     * @return string
     */
    public function actionCreatedone($motionSlug, $fromMode)
    {
        $motion = $this->consultation->getMotion($motionSlug);
        return $this->render('create_done', ['motion' => $motion, 'mode' => $fromMode]);
    }

    /**
     * @param string $motionSlug
     * @param string $fromMode
     * @return string
     * @throws Internal
     */
    public function actionCreateconfirm($motionSlug, $fromMode)
    {
        $motion = $this->consultation->getMotion($motionSlug);
        if (!$motion || $motion->status != Motion::STATUS_DRAFT) {
            \Yii::$app->session->setFlash('error', \Yii::t('motion', 'err_not_found'));
            return $this->redirect(UrlHelper::createUrl('consultation/index'));
        }

        if ($this->isPostSet('modify')) {
            return $this->redirect(UrlHelper::createMotionUrl($motion, 'edit'));
        }

        if ($this->isPostSet('confirm')) {
            $motion->trigger(Motion::EVENT_SUBMITTED, new MotionEvent($motion));

            if ($motion->status == Motion::STATUS_SUBMITTED_SCREENED) {
                $motion->trigger(Motion::EVENT_PUBLISHED, new MotionEvent($motion));
            } else {
                EmailNotifications::sendMotionSubmissionConfirm($motion);
            }

            if (User::getCurrentUser()) {
                UserNotification::addNotification(
                    User::getCurrentUser(),
                    $this->consultation,
                    UserNotification::NOTIFICATION_AMENDMENT_MY_MOTION
                );
            }

            return $this->redirect(UrlHelper::createMotionUrl($motion, 'createdone', ['fromMode' => $fromMode]));
        } else {
            $params                  = ['motion' => $motion, 'mode' => $fromMode];
            $params['deleteDraftId'] = $this->getRequestValue('draftId');
            return $this->render('create_confirm', $params);
        }
    }

    /**
     * @param string $motionSlug
     * @return string
     * @throws FormError
     */
    public function actionEdit($motionSlug)
    {
        $motion = $this->consultation->getMotion($motionSlug);
        if (!$motion) {
            \Yii::$app->session->setFlash('error', \Yii::t('motion', 'err_not_found'));
            return $this->redirect(UrlHelper::createUrl('consultation/index'));
        }

        if (!$motion->canEdit()) {
            \Yii::$app->session->setFlash('error', \Yii::t('motion', 'err_edit_permission'));
            return $this->redirect(UrlHelper::createUrl('consultation/index'));
        }

        $form     = new MotionEditForm($motion->motionType, $motion->agendaItem, $motion);
        $fromMode = ($motion->status == Motion::STATUS_DRAFT ? 'create' : 'edit');

        if ($this->isPostSet('save')) {
            $post = \Yii::$app->request->post();
            $motion->flushCacheWithChildren();
            $form->setAttributes([$post, $_FILES]);
            try {
                $form->saveMotion($motion);
                if (isset($post['sections'])) {
                    $form->updateTextRewritingAmendments($motion, $post['sections']);
                }

                ConsultationLog::logCurrUser($this->consultation, ConsultationLog::MOTION_CHANGE, $motion->id);

                if ($motion->status == Motion::STATUS_DRAFT) {
                    $nextUrl = UrlHelper::createMotionUrl($motion, 'createconfirm', ['fromMode' => $fromMode]);
                    return $this->redirect($nextUrl);
                } else {
                    return $this->render('edit_done', ['motion' => $motion]);
                }
            } catch (FormError $e) {
                \Yii::$app->session->setFlash('error', $e->getMessage());
                $form->setSectionTextWithoutSaving($motion, $post['sections']);
            }
        }

        return $this->render(
            'edit_form',
            [
                'mode'         => $fromMode,
                'form'         => $form,
                'consultation' => $this->consultation,
            ]
        );
    }

    /**
     * @param int $motionTypeId
     * @param int $agendaItemId
     * @param int $cloneFrom
     * @return array
     * @throws Internal
     * @throws \app\models\exceptions\NotFound
     */
    private function getMotionTypeForCreate($motionTypeId = 0, $agendaItemId = 0, $cloneFrom = 0)
    {
        if ($agendaItemId > 0) {
            $where      = ['consultationId' => $this->consultation->id, 'id' => $agendaItemId];
            $agendaItem = ConsultationAgendaItem::findOne($where);
            if (!$agendaItem) {
                throw new Internal('Could not find agenda item');
            }
            /** @var ConsultationAgendaItem $agendaItem */
            if (!$agendaItem->motionType) {
                throw new Internal('Agenda item does not have motions');
            }
            $motionType = $agendaItem->motionType;
        } elseif ($motionTypeId > 0) {
            $motionType = $this->consultation->getMotionType($motionTypeId);
            $agendaItem = null;
        } elseif ($cloneFrom > 0) {
            $motion = $this->consultation->getMotion($cloneFrom);
            if (!$motion) {
                throw new Internal('Could not find referenced motion');
            }
            $motionType = $motion->motionType;
            $agendaItem = $motion->agendaItem;
        } else {
            throw new Internal('Could not resolve motion type');
        }

        return [$motionType, $agendaItem];
    }


    /**
     * @param int $motionTypeId
     * @param int $agendaItemId
     * @param int $cloneFrom
     * @return string
     * @throws Internal
     * @throws \yii\base\ExitException
     */
    public function actionCreate($motionTypeId = 0, $agendaItemId = 0, $cloneFrom = 0)
    {
        try {
            $ret = $this->getMotionTypeForCreate($motionTypeId, $agendaItemId, $cloneFrom);
            list($motionType, $agendaItem) = $ret;
        } catch (ExceptionBase $e) {
            \Yii::$app->session->setFlash('error', $e->getMessage());
            return $this->redirect(UrlHelper::createUrl('consultation/index'));
        }

        /**
         * @var ConsultationMotionType $motionType
         * @var ConsultationAgendaItem|null $agendaItem
         */

        $policy = $motionType->getMotionPolicy();
        if (!$policy->checkCurrUserMotion()) {
            if ($policy->checkCurrUserMotion(true, true)) {
                $loginUrl = UrlHelper::createLoginUrl([
                    'motion/create',
                    'motionTypeId' => $motionTypeId,
                    'agendaItemId' => $agendaItemId
                ]);
                return $this->redirect($loginUrl);
            } else {
                return $this->showErrorpage(403, \Yii::t('motion', 'err_create_permission'));
            }
        }

        $form        = new MotionEditForm($motionType, $agendaItem, null);
        $supportType = $motionType->getMotionSupportTypeClass();
        $iAmAdmin    = User::havePrivilege($this->consultation, User::PRIVILEGE_SCREENING);

        if ($this->isPostSet('save')) {
            try {
                $motion = $form->createMotion();

                // Supporting members are not collected in the form, but need to be copied a well
                if ($supportType->collectSupportersBeforePublication() && $cloneFrom && $iAmAdmin) {
                    $adoptMotion = $this->consultation->getMotion($cloneFrom);
                    foreach ($adoptMotion->motionSupporters as $supp) {
                        if ($supp->role == MotionSupporter::ROLE_SUPPORTER) {
                            $suppNew = new MotionSupporter();
                            $suppNew->setAttributes($supp->getAttributes());
                            $suppNew->id       = null;
                            $suppNew->motionId = $motion->id;
                            $suppNew->save();
                        }
                    }
                }

                return $this->redirect(UrlHelper::createMotionUrl($motion, 'createconfirm', [
                    'fromMode' => 'create',
                    'draftId'  => $this->getRequestValue('draftId'),
                ]));
            } catch (FormError $e) {
                \Yii::$app->session->setFlash('error', $e->getMessage());
            }
        } elseif ($cloneFrom > 0) {
            $motion = $this->consultation->getMotion($cloneFrom);
            $form->cloneSupporters($motion);
            $form->cloneMotionText($motion);
        }


        if (count($form->supporters) == 0) {
            $supporter       = new MotionSupporter();
            $supporter->role = MotionSupporter::ROLE_INITIATOR;
            $iAmAdmin        = User::havePrivilege($this->consultation, User::PRIVILEGE_SCREENING);
            if (User::getCurrentUser() && !$iAmAdmin) {
                $user                    = User::getCurrentUser();
                $supporter->userId       = $user->id;
                $supporter->name         = $user->name;
                $supporter->contactEmail = $user->email;
                $supporter->personType   = MotionSupporter::PERSON_NATURAL;
            }
            $form->supporters[] = $supporter;
        }

        return $this->render(
            'edit_form',
            [
                'mode'         => 'create',
                'form'         => $form,
                'consultation' => $this->consultation,
            ]
        );
    }


    /**
     * @param string $motionSlug
     * @return string
     */
    public function actionWithdraw($motionSlug)
    {
        $motion = $this->consultation->getMotion($motionSlug);
        if (!$motion) {
            \Yii::$app->session->setFlash('error', \Yii::t('motion', 'err_not_found'));
            return $this->redirect(UrlHelper::createUrl('consultation/index'));
        }

        if (!$motion->canWithdraw()) {
            \Yii::$app->session->setFlash('error', \Yii::t('motion', 'err_withdraw_permission'));
            return $this->redirect(UrlHelper::createUrl('consultation/index'));
        }

        if ($this->isPostSet('cancel')) {
            return $this->redirect(UrlHelper::createMotionUrl($motion));
        }

        if ($this->isPostSet('withdraw')) {
            $motion->withdraw();
            \Yii::$app->session->setFlash('success', \Yii::t('motion', 'withdraw_done'));
            return $this->redirect(UrlHelper::createMotionUrl($motion));
        }

        return $this->render('withdraw', ['motion' => $motion]);
    }

    /**
     * @param string $motionSlug
     * @return string
     * @throws \app\models\exceptions\Internal
     */
    public function actionSaveProposalStatus($motionSlug)
    {
        \yii::$app->response->format = Response::FORMAT_RAW;
        \yii::$app->response->headers->add('Content-Type', 'application/json');

        $motion = $this->consultation->getMotion($motionSlug);
        if (!$motion) {
            \Yii::$app->response->statusCode = 404;
            return 'Amendment not found';
        }
        if (!User::havePrivilege($this->consultation, User::PRIVILEGE_CHANGE_PROPOSALS)) {
            \Yii::$app->response->statusCode = 403;
            return 'Not permitted to change the status';
        }

        $response = [];
        $msgAlert = null;

        if (\Yii::$app->request->post('setStatus', null) !== null) {
            if ($motion->proposalStatus != \Yii::$app->request->post('setStatus', null)) {
                if ($motion->proposalUserStatus !== null) {
                    $msgAlert = \Yii::t('amend', 'proposal_user_change_reset');
                }
                $motion->proposalUserStatus = null;
            }
            $motion->proposalStatus  = \Yii::$app->request->post('setStatus');
            $motion->proposalComment = \Yii::$app->request->post('proposalComment', '');
            $motion->votingStatus    = \Yii::$app->request->post('votingStatus', '');
            if (\Yii::$app->request->post('proposalExplanation', null) !== null) {
                if (trim(\Yii::$app->request->post('proposalExplanation', '') === '')) {
                    $motion->proposalExplanation = null;
                } else {
                    $motion->proposalExplanation = \Yii::$app->request->post('proposalExplanation', '');
                }
            } else {
                $motion->proposalExplanation = null;
            }
            if (\Yii::$app->request->post('visible', 0)) {
                $motion->setProposalPublished();
            } else {
                $motion->proposalVisibleFrom = null;
            }
            $votingBlockId         = \Yii::$app->request->post('votingBlockId', null);
            $motion->votingBlockId = null;
            if ($votingBlockId === 'NEW') {
                $title = trim(\Yii::$app->request->post('votingBlockTitle', ''));
                if ($title !== '') {
                    $votingBlock                 = new VotingBlock();
                    $votingBlock->consultationId = $this->consultation->id;
                    $votingBlock->title          = $title;
                    $votingBlock->votingStatus   = IMotion::STATUS_VOTE;
                    $votingBlock->save();

                    $motion->votingBlockId = $votingBlock->id;
                }
            } elseif ($votingBlockId > 0) {
                $votingBlock = $this->consultation->getVotingBlock($votingBlockId);
                if ($votingBlock) {
                    $motion->votingBlockId = $votingBlock->id;
                }
            }

            $response['success'] = false;
            if ($motion->save()) {
                $response['success'] = true;
            }

            $this->consultation->refresh();
            $response['html']        = $this->renderPartial('_set_proposed_procedure', [
                'motion'   => $motion,
                'msgAlert' => $msgAlert,
            ]);
            $response['proposalStr'] = $motion->getFormattedProposalStatus(true);
        }

        if (\Yii::$app->request->post('notifyProposer')) {
            try {
                new MotionProposedProcedure($motion);
                $motion->proposalNotification = date('Y-m-d H:i:s');
                $motion->save();
                $response['success'] = true;
                $response['html']    = $this->renderPartial('_set_proposed_procedure', [
                    'motion'   => $motion,
                    'msgAlert' => $msgAlert,
                    'context'  => \Yii::$app->request->post('context', 'view'),
                ]);
            } catch (MailNotSent $e) {
                $response['success'] = false;
                $response['error']   = 'The mail could not be sent: ' . $e->getMessage();
            }
        }

        if (\Yii::$app->request->post('writeComment')) {
            $adminComment               = new MotionAdminComment();
            $adminComment->userId       = User::getCurrentUser()->id;
            $adminComment->text         = \Yii::$app->request->post('writeComment');
            $adminComment->status       = MotionAdminComment::PROPOSED_PROCEDURE;
            $adminComment->dateCreation = date('Y-m-d H:i:s');
            $adminComment->motionId     = $motion->id;
            if (!$adminComment->save()) {
                \Yii::$app->response->statusCode = 500;
                $response['success']             = false;
                return json_encode($response);
            }

            $response['success'] = true;
            $response['comment'] = [
                'username'      => $adminComment->user->name,
                'id'            => $adminComment->id,
                'text'          => $adminComment->text,
                'delLink'       => UrlHelper::createMotionUrl($motion, 'del-proposal-comment'),
                'dateFormatted' => Tools::formatMysqlDateTime($adminComment->dateCreation),
            ];
        }

        return json_encode($response);
    }

    /**
     * @param string $prefix
     * @return null|string
     */
    protected function guessRedirectByPrefix($prefix)
    {
        $motion = Motion::findOne([
            'consultationId' => $this->consultation->id,
            'titlePrefix'    => $prefix
        ]);
        if ($motion && $motion->isReadable()) {
            return $motion->getLink();
        }

        /** @var Amendment|null $amendment */
        $amendment = Amendment::find()->joinWith('motionJoin')->where([
            'motion.consultationId' => $this->consultation->id,
            'amendment.titlePrefix' => $prefix,
        ])->one();

        if ($amendment && $amendment->isReadable()) {
            return $amendment->getLink();
        }

        return null;
    }

    /**
     * URL: /[consultationPrefix]/[motionPrefix]
     *
     * @param string $prefix
     * @return \yii\console\Response|Response
     * @throws NotFoundHttpException
     */
    public function actionGotoPrefix($prefix)
    {
        $redirect = $this->guessRedirectByPrefix($prefix);
        if ($redirect) {
            return \Yii::$app->response->redirect($redirect);
        }
        throw new NotFoundHttpException();
    }
}
