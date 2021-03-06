<?php

namespace app\plugins\memberPetitions;

use app\components\UrlHelper;
use app\models\db\Motion;
use app\models\layoutHooks\HooksAdapter;
use yii\helpers\Html;

class LayoutHooks extends HooksAdapter
{
    /**
     * @param $before
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function logoRow($before)
    {
        $out = '<header class="row logo" role="banner">' .
            '<p id="logo"><a href="' . Html::encode(UrlHelper::homeUrl()) . '" class="homeLinkLogo" title="' .
            Html::encode(\Yii::t('base', 'home_back')) . '">' .
            $this->layout->getLogoStr() .
            '</a></p>' .
            '<div class="hgroup">' .
            '<div id="site-title"><span>' .
            '<a href="' . Html::encode(UrlHelper::homeUrl()) . '" rel="home">' .
            \Yii::t('memberPetitions', 'title') . '</a>' .
            '</span></div>';
        if ($this->consultation) {
            $out .= '<div id="site-description" class="siteDescriptionPetition">' .
                Html::encode($this->consultation->title) . '</div>';
        }
        $out .= '</div>' .
            '</header>';

        return $out;
    }

    /**
     * @param string $before
     * @param Motion $motion
     * @return string
     */
    public function beforeMotionView($before, Motion $motion)
    {
        if (Tools::canRespondToPetition($motion)) {
            $before .= '<div class="content"><div class="alert alert-info">';
            $before .= \Yii::t('memberPetitions', 'answer_hint');
            $before .= '</div></div>';
        }

        if ($motion->canMergeAmendments()) {
            $before .= '<div class="content"><div class="alert alert-info">';
            $before .= \Yii::t('memberPetitions', 'discussion_over');
            $before .= '<div style="text-align: center; margin-top: 15px;">' . Html::a(
                \Yii::t('memberPetitions', 'discussion_over_btn'),
                UrlHelper::createMotionUrl($motion, 'merge-amendments-init'),
                ['class' => 'btn btn-primary']
            ) . '</div>';
            $before .= '</div></div>';
        }

        return $before;
    }

    /**
     * @param string $before
     * @param Motion $motion
     * @return string
     */
    public function afterMotionView($before, Motion $motion)
    {
        if (Tools::canRespondToPetition($motion)) {
            $this->layout->loadCKEditor();
            $before .= \Yii::$app->controller->renderPartial('@app/plugins/memberPetitions/views/_respond', [
                'motion' => $motion,
            ]);
        }

        $response = Tools::getMotionResponse($motion);
        if ($response) {
            $before .= \Yii::$app->controller->renderPartial('@app/plugins/memberPetitions/views/_response', [
                'motion'   => $motion,
                'response' => $response,
            ]);
        }

        return $before;
    }

    /**
     * @param array $motionData
     * @param Motion $motion
     * @return array
     * @throws \app\models\exceptions\Internal
     * @throws \Exception
     */
    public function getMotionViewData($motionData, Motion $motion)
    {
        $deadline = Tools::getPetitionResponseDeadline($motion);
        if ($deadline) {
            $deadlineStr = \app\components\Tools::formatMysqlDate($deadline->format('Y-m-d'));
            if (Tools::isMotionDeadlineOver($motion)) {
                $deadlineStr .= ' (' . \Yii::t('memberPetitions', 'response_overdue') . ')';
            }
            $motionData[] = [
                'title'   => \Yii::t('memberPetitions', 'response_deadline'),
                'content' => $deadlineStr,
            ];
        }

        $discussionUntil = Tools::getDiscussionUntil($motion);
        if ($discussionUntil) {
            $motionData[] = [
                'title'   => \Yii::t('memberPetitions', 'discussion_until'),
                'content' => \app\components\Tools::formatMysqlDate($discussionUntil->format('Y-m-d')),
            ];
        }
        return $motionData;
    }

    /**
     * @param string $before
     * @param Motion $motion
     * @return string
     */
    public function getFormattedMotionStatus($before, Motion $motion)
    {
        if ($motion->motionTypeId === Tools::getDiscussionType($motion->getMyConsultation())->id) {
            switch ($motion->status) {
                case Motion::STATUS_SUBMITTED_SCREENED:
                    return \Yii::t('memberPetitions', 'status_discussing');
            }
        }
        if ($motion->motionTypeId === Tools::getPetitionType($motion->getMyConsultation())->id) {
            switch ($motion->status) {
                case Motion::STATUS_COLLECTING_SUPPORTERS:
                    return \Yii::t('memberPetitions', 'status_collecting');
                case Motion::STATUS_SUBMITTED_SCREENED:
                    return \Yii::t('memberPetitions', 'status_unanswered');
                case Motion::STATUS_PROCESSED:
                    return '✔ ' . \Yii::t('memberPetitions', 'status_answered');
            }
        }
        return $before;
    }
}
