<?php

namespace app\plugins\memberPetitions;

use app\models\db\Consultation;
use app\models\db\IMotion;
use app\models\db\Motion;
use app\models\db\MotionSupporter;
use app\models\db\Site;
use app\models\db\User;
use app\models\events\MotionEvent;
use app\models\supportTypes\ISupportType;
use app\plugins\memberPetitions\notifications\DiscussionSubmitted;
use app\plugins\memberPetitions\notifications\PetitionSubmitted;
use app\components\Tools as DateTools;

class Tools
{
    /**
     * @param Site $site
     * @param User $user
     * @return Consultation[]
     */
    public static function getUserConsultations($site, $user)
    {
        if (!$user) {
            return [];
        }

        $organizations = $user->getMyOrganizationIds();
        $consultations = [];
        foreach ($site->consultations as $consultation) {
            /** @var ConsultationSettings $settings */
            $settings = $consultation->getSettings();

            if ($settings->maintenanceMode && !$user->hasPrivilege($consultation, User::PRIVILEGE_ANY)) {
                continue;
            }

            if (in_array($settings->organizationId, $organizations)) {
                $consultations[] = $consultation;
            }
        }
        return $consultations;
    }

    /**
     * @param Consultation $consultation
     * @return \app\models\db\ConsultationMotionType|null
     */
    public static function getDiscussionType(Consultation $consultation)
    {
        foreach ($consultation->motionTypes as $motionType) {
            if ($motionType->supportType !== ISupportType::COLLECTING_SUPPORTERS) {
                return $motionType;
            }
        }
        return null;
    }

    /**
     * @param Consultation $consultation
     * @return \app\models\db\ConsultationMotionType|null
     */
    public static function getPetitionType(Consultation $consultation)
    {
        foreach ($consultation->motionTypes as $motionType) {
            if ($motionType->supportType === ISupportType::COLLECTING_SUPPORTERS) {
                return $motionType;
            }
        }
        return null;
    }

    /**
     * @param Consultation $consultation
     * @return bool
     */
    public static function isConsultationFullyConfigured(Consultation $consultation)
    {
        return (static::getPetitionType($consultation) !== null && static::getDiscussionType($consultation) !== null);
    }

    /**
     * @param Consultation[] $consultations
     * @return Motion[]
     */
    public static function getAllMotions($consultations)
    {
        $all = [];
        foreach ($consultations as $consultation) {
            if (!Tools::isConsultationFullyConfigured($consultation)) {
                continue;
            }
            $all = array_merge($all, static::getPetitionType($consultation)->getVisibleMotions(false));
            $all = array_merge($all, static::getMotionsCollecting($consultation));
            $all = array_merge($all, static::getDiscussionType($consultation)->getVisibleMotions(false));
            $all = array_merge($all, static::getMotionsAnswered($consultation));
        }
        return $all;
    }

    /**
     * @param Consultation $consultation
     * @return Motion[]
     */
    public static function getMotionsInDiscussion(Consultation $consultation)
    {
        $motions = Tools::getDiscussionType($consultation)->getVisibleMotions(false);
        return array_filter($motions, function (Motion $motion) {
            return ($motion->status == IMotion::STATUS_SUBMITTED_SCREENED);
        });
    }

    /**
     * @param Consultation[] $consultations
     * @return Motion[]
     */
    public static function getAllMotionsInDiscussion($consultations)
    {
        $all = [];
        foreach ($consultations as $consultation) {
            if (!Tools::isConsultationFullyConfigured($consultation)) {
                continue;
            }
            $all = array_merge($all, static::getMotionsInDiscussion($consultation));
        }
        return $all;
    }

    /**
     * @param Consultation $consultation
     * @return Motion[]
     */
    public static function getMotionsAnswered(Consultation $consultation)
    {
        $motions = Tools::getPetitionType($consultation)->getVisibleMotions(true); // true: wg. processed
        return array_filter($motions, function (Motion $motion) {
            return ($motion->status == IMotion::STATUS_PROCESSED);
        });
    }

    /**
     * @param Consultation[] $consultations
     * @return Motion[]
     */
    public static function getAllMotionsAnswered($consultations)
    {
        $all = [];
        foreach ($consultations as $consultation) {
            if (!Tools::isConsultationFullyConfigured($consultation)) {
                continue;
            }
            $all = array_merge($all, static::getMotionsAnswered($consultation));
        }
        return $all;
    }

    /**
     * @param Consultation $consultation
     * @return Motion[]
     */
    public static function getMotionsUnanswered(Consultation $consultation)
    {
        $motions = Tools::getPetitionType($consultation)->getVisibleMotions(false);
        return array_filter($motions, function (Motion $motion) {
            return ($motion->status != IMotion::STATUS_PROCESSED);
        });
    }

    /**
     * @param Consultation[] $consultations
     * @return Motion[]
     */
    public static function getAllMotionsUnanswered($consultations)
    {
        $all = [];
        foreach ($consultations as $consultation) {
            if (!Tools::isConsultationFullyConfigured($consultation)) {
                continue;
            }
            $all = array_merge($all, static::getMotionsUnanswered($consultation));
        }
        return $all;
    }

    /**
     * @param Consultation $consultation
     * @return Motion[]
     */
    public static function getMotionsCollecting(Consultation $consultation)
    {
        $motions = Tools::getPetitionType($consultation)->motions; // Collecting phase is not visible by default
        return array_filter($motions, function (Motion $motion) {
            return ($motion->status == IMotion::STATUS_COLLECTING_SUPPORTERS);
        });
    }

    /**
     * @param Consultation[] $consultations
     * @return Motion[]
     */
    public static function getAllMotionsCollection($consultations)
    {
        $all = [];
        foreach ($consultations as $consultation) {
            if (!Tools::isConsultationFullyConfigured($consultation)) {
                continue;
            }
            $all = array_merge($all, static::getMotionsCollecting($consultation));
        }
        return $all;
    }

    /**
     * @param Site $site
     * @return Motion[]
     */
    public static function getMyMotions(Site $site)
    {
        $motions = [];
        $user    = User::getCurrentUser();
        if (!$user) {
            return $motions;
        }

        foreach ($site->consultations as $consultation) {
            foreach ($consultation->motions as $motion) {
                if ($motion->status === Motion::STATUS_INLINE_REPLY) {
                    continue;
                }
                if (count($motion->replacedByMotions) > 0) {
                    continue;
                }
                if ($motion->iAmInitiator() && $motion->isVisibleForAdmins()) {
                    $motions[] = $motion;
                }
            }
        }

        return $motions;
    }

    /**
     * @param Site $site
     * @return Motion[]
     */
    public static function getSupportedMotions(Site $site)
    {
        $motions = [];
        $user    = User::getCurrentUser();
        if (!$user) {
            return $motions;
        }

        foreach ($site->consultations as $consultation) {
            foreach ($consultation->motions as $motion) {
                foreach ($motion->getSupporters() as $supporter) {
                    if ($supporter->userId == $user->id && !in_array($motion, $motions, true)) {
                        $motions[] = $motion;
                    }
                }
            }
        }

        return $motions;
    }

    /**
     * @param IMotion $motion
     * @return bool
     */
    public static function canRespondToPetition(IMotion $motion)
    {
        $typePetition = Tools::getPetitionType($motion->getMyConsultation());
        if ($motion->getMyMotionType()->id !== $typePetition->id) {
            return false;
        }
        if (!$motion->isVisible() || $motion->status === IMotion::STATUS_PROCESSED) {
            return false;
        }

        $user = User::getCurrentUser();
        return ($user && $user->hasPrivilege($motion->getMyConsultation(), User::PRIVILEGE_CONTENT_EDIT));
    }

    /**
     * @param Motion $motion
     * @return null|Motion
     */
    public static function getMotionResponse(Motion $motion)
    {
        if ($motion->status !== IMotion::STATUS_PROCESSED) {
            return null;
        }
        return Motion::findOne([
            'parentMotionId' => $motion->id,
            'status'         => Motion::STATUS_INLINE_REPLY,
        ]);
    }

    /**
     * @param IMotion $motion
     * @return \DateTime|null
     * @throws \Exception
     */
    public static function getPetitionResponseDeadline(IMotion $motion)
    {
        $typePetition = Tools::getPetitionType($motion->getMyConsultation());
        if ($motion->getMyMotionType()->id !== $typePetition->id) {
            return null;
        }
        if (!$motion->isVisible() || $motion->status === IMotion::STATUS_PROCESSED) {
            return null;
        }
        if (!$motion->datePublication) {
            return null;
        }
        $date = new \DateTime($motion->datePublication);
        /** @var ConsultationSettings $settings */
        $settings = $motion->getMyConsultation()->getSettings();
        $date->add(new \DateInterval('P' . $settings->replyDeadline . "D"));

        return $date;
    }

    /**
     * @param IMotion $motion
     * @return bool
     * @throws \Exception
     */
    public static function isMotionDeadlineOver(IMotion $motion)
    {
        $deadline = static::getPetitionResponseDeadline($motion);
        if (!$deadline) {
            return false;
        }
        return $deadline->getTimestamp() < time();
    }

    /**
     * @param IMotion $motion
     * @return \DateTime|null
     * @throws \Exception
     */
    public static function getDiscussionUntil(IMotion $motion)
    {
        $typeDiscussion = Tools::getDiscussionType($motion->getMyConsultation());
        if ($motion->getMyMotionType()->id !== $typeDiscussion->id) {
            return null;
        }
        if (!$motion->isVisible() || $motion->status !== IMotion::STATUS_SUBMITTED_SCREENED) {
            return null;
        }
        if (!$motion->datePublication) {
            return null;
        }
        $date = new \DateTime($motion->datePublication);
        /** @var ConsultationSettings $settings */
        $settings = $motion->getMyConsultation()->getSettings();
        $date->add(new \DateInterval('P' . $settings->minDiscussionTime . "D"));

        return $date;
    }

    /**
     * @param IMotion $motion
     * @return int
     */
    public static function getMotionPhaseNumber(IMotion $motion)
    {
        if ($motion->getMyMotionType()->id === Tools::getDiscussionType($motion->getMyConsultation())->id) {
            return 1; // In Discussion
        } elseif ($motion->getMyMotionType()->id === Tools::getPetitionType($motion->getMyConsultation())->id) {
            if ($motion->status === IMotion::STATUS_COLLECTING_SUPPORTERS) {
                return 2; // Collecting
            } elseif ($motion->status === IMotion::STATUS_PROCESSED) {
                return 4; // Answered
            } else {
                return 3; // Waiting for answer
            }
        } else {
            return 0;
        }
    }

    /**
     * @param IMotion $motion
     * @return bool
     * @throws \Exception
     */
    public static function isDiscussionUntilOver(IMotion $motion)
    {
        $deadline = static::getDiscussionUntil($motion);
        if (!$deadline) {
            return false;
        }
        return $deadline->getTimestamp() < time();
    }

    /**
     * @param MotionEvent $event
     * @throws \app\models\exceptions\FormError
     */
    public static function onMerged(MotionEvent $event)
    {
        $motion = $event->motion;
        if ($motion->motionTypeId !== static::getDiscussionType($motion->getMyConsultation())->id) {
            return;
        }

        $motion->setMotionType(static::getPetitionType($motion->getMyConsultation()));

        $motion->status          = Motion::STATUS_COLLECTING_SUPPORTERS;
        $motion->datePublication = null;
        $motion->save();

        if (count($motion->getInitiators()) === 0 && $motion->replacedMotion) {
            foreach ($motion->replacedMotion->getInitiators() as $initiator) {
                $newInitiator = new MotionSupporter();
                $newInitiator->setAttributes($initiator->getAttributes(), false);
                $newInitiator->id       = null;
                $newInitiator->motionId = $motion->id;
                $newInitiator->save();
            }
        }
    }

    /**
     * @param MotionEvent $event
     * @throws \app\models\exceptions\MailNotSent
     * @throws \app\models\exceptions\ServerConfiguration
     */
    public static function onPublishedFirst(MotionEvent $event)
    {
        $motion = $event->motion;
        if ($motion->motionTypeId === static::getDiscussionType($motion->getMyConsultation())->id) {
            new DiscussionSubmitted($motion);
        }
        if ($motion->motionTypeId === static::getPetitionType($motion->getMyConsultation())->id) {
            new PetitionSubmitted($motion);
        }
    }

    /**
     * @param Motion[] $motions
     * @return array
     */
    public static function getMostPopularTags($motions)
    {
        $tags = [];
        foreach ($motions as $motion) {
            foreach ($motion->tags as $tag) {
                if (!isset($tags[$tag->id])) {
                    $tags[$tag->id] = [
                        'id'    => $tag->id,
                        'title' => $tag->title,
                        'num'   => 0,
                    ];
                }
                $tags[$tag->id]['num']++;
            }
        }
        $tags = array_values($tags);
        usort($tags, function ($tag1, $tag2) {
            if ($tag1['num'] > $tag2['num']) {
                return -1;
            }
            if ($tag1['num'] < $tag2['num']) {
                return 1;
            }
            return 0;
        });
        return $tags;
    }

    /**
     * @param IMotion $motion
     * @return int
     */
    public static function getMotionTimestamp(IMotion $motion)
    {
        if ($motion->datePublication) {
            return DateTools::dateSql2timestamp($motion->datePublication);
        } elseif ($motion->dateCreation) {
            return DateTools::dateSql2timestamp($motion->dateCreation);
        } else {
            return 0;
        }
    }
}
