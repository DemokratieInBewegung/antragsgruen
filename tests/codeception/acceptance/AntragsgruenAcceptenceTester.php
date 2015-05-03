<?php

// @codingStandardsIgnoreFile
use tests\codeception\_pages\AdminIndexPage;
use tests\codeception\_pages\ConsultationHomePage;
use tests\codeception\_pages\MotionPage;

/**
 * Class AntragsgruenAcceptenceTester
 * @SuppressWarnings(PHPMD)
 */
class AntragsgruenAcceptenceTester extends AcceptanceTester
{
    use \app\tests\AntragsgruenSetupDB;

    /**
     * @param \Codeception\Scenario $scenario
     */
    public function __construct(\Codeception\Scenario $scenario)
    {
        parent::__construct($scenario);
        $this->createDB();
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->deleteDB();
    }

    /**
     *
     */
    public function populateDBData1()
    {
        $this->populateDB(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'fixtures' .
            DIRECTORY_SEPARATOR . 'dbdata1.sql');
    }

    /**
     * @param bool $check
     * @return ConsultationHomePage
     */
    public function gotoStdConsultationHome($check = true)
    {
        $page = ConsultationHomePage::openBy(
            $this,
            [
                'subdomain'        => 'stdparteitag',
                'consultationPath' => 'std-parteitag',
            ]
        );
        if ($check) {
            $this->see('Test2', 'h1');
        }
        return $page;
    }

    /**
     * @param bool $check
     * @param int $motionId
     * @return MotionPage
     */
    public function gotoMotion($check = true, $motionId = 2)
    {
        $page = MotionPage::openBy(
            $this,
            [
                'subdomain'        => 'stdparteitag',
                'consultationPath' => 'std-parteitag',
                'motionId'         => $motionId,
            ]
        );
        if ($check) {
            $this->seeElement('.motionData');
        }
        return $page;
    }

    /**
     * @param bool $check
     * @return AdminIndexPage
     */
    public function gotoStdAdminPage($check = true)
    {
        $page = AdminIndexPage::openBy(
            $this,
            [
                'subdomain'        => 'stdparteitag',
                'consultationPath' => 'std-parteitag',
            ]
        );
        return $page;
    }

    /**
     *
     */
    public function loginAsStdAdmin()
    {
        $this->see('LOGIN', '#loginLink');
        $this->click('#loginLink');

        $this->see('LOGIN', 'h1');
        $this->fillField('#username', 'testadmin@example.org');
        $this->fillField('#password_input', 'testadmin');
        $this->submitForm('#usernamePasswordForm', [], 'loginusernamepassword');
    }

    /**
     *
     */
    public function loginAsStdUser()
    {
        $this->see('LOGIN', '#loginLink');
        $this->click('#loginLink');

        $this->see('LOGIN', 'h1');
        $this->fillField('#username', 'testuser@example.org');
        $this->fillField('#password_input', 'testuser');
        $this->submitForm('#usernamePasswordForm', [], 'loginusernamepassword');
    }

    /**
     *
     */
    public function logout()
    {
        $this->see('LOGOUT', '#logoutLink');
        $this->click('#logoutLink');
    }
}
