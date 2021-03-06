<?php
use app\tests\_pages\AdminIndexPage;
use app\tests\_pages\AdminMotionListPage;
use app\tests\_pages\AmendmentPage;
use app\tests\_pages\ConsultationHomePage;
use app\tests\_pages\MotionPage;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(PHPMD)
 */
class AcceptanceTester extends \Codeception\Actor
{
    use _generated\AcceptanceTesterActions;

    const FIRST_FREE_MOTION_ID              = 121;
    const FIRST_FREE_MOTION_TITLE_PREFIX    = 'A9';
    const FIRST_FREE_AMENDMENT_TITLE_PREFIX = 'Ä8';
    const FIRST_FREE_MOTION_SECTION         = 51;
    const FIRST_FREE_AMENDMENT_ID           = 283;
    const FIRST_FREE_AGENDA_ITEM_ID         = 15;
    const FIRST_FREE_COMMENT_ID             = 1;
    const FIRST_FREE_MOTION_TYPE            = 17;
    const FIRST_FREE_CONSULTATION_ID        = 11;
    const FIRST_FREE_VOTING_BLOCK_ID        = 3;
    const FIRST_FREE_CONTENT_ID             = 4;

    const ABSOLUTE_URL_TEMPLATE = 'http://antragsgruen-test.local/{SUBDOMAIN}/{CONSULTATION}/{PATH}';

    public static $ACCEPTED_HTML_ERRORS = [
        'Bad value “popup” for attribute “rel”',
        'CKEDITOR',
        'autocomplete'
    ];

    /**
     * @param bool $check
     * @param string $subdomain
     * @param string $path
     * @return ConsultationHomePage
     */
    public function gotoConsultationHome($check = true, $subdomain = 'stdparteitag', $path = 'std-parteitag')
    {
        $page = ConsultationHomePage::openBy(
            $this,
            [
                'subdomain'        => $subdomain,
                'consultationPath' => $path,
            ]
        );
        if ($check && $subdomain == 'stdparteitag' && $path == 'std-parteitag') {
            $this->see('Test2', 'h1');
        }
        return $page;
    }

    /**
     * @param bool $check
     * @param string $motionSlug
     * @return MotionPage
     */
    public function gotoMotion($check = true, $motionSlug = '2')
    {
        if (is_numeric($motionSlug)) {
            /** @var \app\models\db\Motion $motion */
            $motion     = \app\models\db\Motion::findOne($motionSlug);
            $motionSlug = $motion->getMotionSlug();
        }
        $page = MotionPage::openBy(
            $this,
            [
                'subdomain'        => 'stdparteitag',
                'consultationPath' => 'std-parteitag',
                'motionSlug'       => $motionSlug,
            ]
        );
        if ($check) {
            $this->seeElement('.motionData');
        }
        $this->wait(0.1);
        return $page;
    }

    /**
     * @param bool $check
     * @param string $motionSlug
     * @param int $amendmentId
     * @return AmendmentPage
     */
    public function gotoAmendment($check = true, $motionSlug = '2', $amendmentId = 1)
    {
        $page = AmendmentPage::openBy(
            $this,
            [
                'subdomain'        => 'stdparteitag',
                'consultationPath' => 'std-parteitag',
                'motionSlug'       => $motionSlug,
                'amendmentId'      => $amendmentId
            ]
        );
        if ($check) {
            $this->seeElement('.motionData');
        }
        $this->wait(0.1);
        return $page;
    }

    /**
     * @param string $subdomain
     * @param string $path
     * @return AdminIndexPage
     */
    public function loginAndGotoStdAdminPage($subdomain = 'stdparteitag', $path = 'std-parteitag')
    {
        $this->gotoConsultationHome(false, $subdomain, $path);
        $this->loginAsStdAdmin();
        return $this->gotoStdAdminPage($subdomain, $path);
    }

    /**
     * @param string $subdomain
     * @param string $path
     * @return AdminMotionListPage
     */
    public function loginAndGotoMotionList($subdomain = 'stdparteitag', $path = 'std-parteitag')
    {
        $this->gotoConsultationHome(false, $subdomain, $path);
        $this->loginAsStdAdmin();
        return $this->gotoMotionList();
    }

    /**
     * @param string $subdomain
     * @param string $path
     * @return AdminIndexPage
     */
    public function gotoStdAdminPage($subdomain = 'stdparteitag', $path = 'std-parteitag')
    {
        $page = AdminIndexPage::openBy(
            $this,
            [
                'subdomain'        => $subdomain,
                'consultationPath' => $path,
            ]
        );
        return $page;
    }

    /**
     * @return AdminMotionListPage
     */
    public function gotoMotionList()
    {
        $this->click('#motionListLink');
        $this->see(mb_strtoupper('Liste: Anträge, Änderungsanträge'), 'h1');
        return new AdminMotionListPage($this);
    }

    /**
     * @param $username
     * @param $password
     * @return $this
     */
    protected function loginWithData($username, $password)
    {
        $this->see('LOGIN', '#loginLink');
        $this->click('#loginLink');

        $this->see('LOGIN', 'h1');
        $this->fillField('#username', $username);
        $this->fillField('#passwordInput', $password);
        $this->submitForm('#usernamePasswordForm', [], 'loginusernamepassword');

        return $this;
    }

    /**
     * @return $this
     */
    public function loginAsStdAdmin()
    {
        return $this->loginWithData('testadmin@example.org', 'testadmin');
    }

    /**
     * @return $this
     */
    public function loginAsConsultationAdmin()
    {
        return $this->loginWithData('consultationadmin@example.org', 'consultationadmin');
    }

    /**
     * @return $this
     */
    public function loginAsProposalAdmin()
    {
        return $this->loginWithData('proposaladmin@example.org', 'proposaladmin');
    }

    /**
     *
     */
    public function loginAsGlobalAdmin()
    {
        return $this->loginWithData('globaladmin@example.org', 'testadmin');
    }

    /**
     *
     */
    public function loginAsStdUser()
    {
        return $this->loginWithData('testuser@example.org', 'testuser');
    }

    /**
     * @return $this
     */
    public function loginAsFixedDataUser()
    {
        return $this->loginWithData('fixeddata@example.org', 'testuser');
    }

    /**
     * @return $this
     */
    public function loginAsFixedDataAdmin()
    {
        return $this->loginWithData('fixedadmin@example.org', 'testadmin');
    }

    /**
     *
     */
    public function loginAsWurzelwerkUser()
    {
        $this->see('LOGIN', '#loginLink');
        $this->click('#loginLink');

        $this->see('LOGIN', 'h1');
        $this->fillField('#wurzelwerkAccount', 'DoeJane');
        $this->submitForm('#wurzelwerkLoginForm', [], 'wurzelwerkLogin');
        $this->seeElement('#logoutLink');
    }

    /**
     *
     */
    public function logout()
    {
        $this->see('LOGOUT', '#logoutLink');
        $this->click('#logoutLink');
    }

    /**
     * @param string $selector
     * @param string $value
     */
    public function selectFueluxOption($selector, $value)
    {
        $this->executeJS('$("' . addslashes($selector) . '")' .
            '.selectlist("selectByValue", "' . addslashes($value) . '")' .
            '.trigger("changed.fu.selectlist");');
    }

    /**
     * @param string $selector
     */
    public function checkFueluxCheckbox($selector)
    {
        $this->executeJS('$("' . addslashes($selector) . '").checkbox("check").find("input").trigger("change");');
    }

    /**
     * @param string $selector
     */
    public function uncheckFueluxCheckbox($selector)
    {
        $this->executeJS('$("' . addslashes($selector) . '").checkbox("uncheck");');
    }
}
