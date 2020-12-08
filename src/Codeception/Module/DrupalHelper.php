<?php

namespace Codeception\Module;

class DrupalHelper extends \Codeception\Module {

  protected $config = [
    'create_dump' => true,
    'admin_username' => 'admin',
    'admin_password' => 'admin',
    'error_message_selectors' => ['.status-message--error', '.messages--error'],
    'exclude_data_tables' => [],
  ];

  /**
   * @var \Codeception\Module\WebDriver
   */
  protected $webDriverModule;

  /**
   * @var \Codeception\Module\Db
   */
  protected $dbModule;

  /**
   * @var \Codeception\Module\AcceptanceHelper
   */
  protected $acceptanceHelperModule;

  /**
   * {@inheritDoc}
   */
  public function _initialize() {
    $this->webDriverModule = $this->getModule('WebDriver');
    $this->dbModule = $this->getModule('Db');
    $this->acceptanceHelperModule = $this->getModule('AcceptanceHelper');
  }

  /**
   * {@inheritDoc}
   */
  public function _beforeSuite($settings = []) {
    $db_module_settings = $this->getEnabledModuleSettings('Db', $settings);

    if ($this->config['create_dump'] && $db_module_settings['populate']) {
      $path_to_drush = str_replace('/', DIRECTORY_SEPARATOR, 'vendor/bin/drush');
      $path_to_dump = str_replace('/', DIRECTORY_SEPARATOR, $db_module_settings['dump']);
      $exclude_data_tables = array_merge([
        'batch',
        'cache_*',
        'cachetags',
        'captcha_sessions',
        'flood',
        'queue',
        'sessions',
        'watchdog',
      ], $this->config['exclude_data_tables']);

      exec($path_to_drush . ' sql-dump --result-file="' . $path_to_dump . '" --structure-tables-list="' . implode(',', $exclude_data_tables) . '"');
    }
  }

  /**
   * Goto to drupal page and check errors.
   */
  public function amOnDrupalPage($url): void {
    if (strpos($url, '://') === FALSE) {
      $this->webDriverModule->amOnPage($url);
    }
    else {
      $this->webDriverModule->amOnUrl($url);
    }
    $this->webDriverModule->seeElementInDOM('body');
    $this->dontSeeErrorMessage();
    $this->dontSeeWatchdogPhpErrors();
  }

  /**
   * Dont see error message.
   */
  public function dontSeeErrorMessage(): void {
    foreach ($this->config['error_message_selectors'] as $error_message_selector) {
      $this->webDriverModule->dontSeeElement($error_message_selector);
    }
  }

  /**
   * Dont see watchdog num errors.
   */
  public function dontSeeWatchdogPhpErrors(): void {
    $this->dbModule->seeNumRecords(0, 'watchdog', ['type' => 'php']);
  }

  /**
   * Login as $username.
   */
  public function login(string $username, string $password): void {
    if ($this->webDriverModule->loadSessionSnapshot('user_' . $username)) {
      return;
    }

    $this->amOnDrupalPage('/user/login');
    $this->webDriverModule->fillField('.user-login-form input[name="name"]', $username);
    $this->webDriverModule->fillField('.user-login-form input[name="pass"]', $password);
    $this->webDriverModule->click('.user-login-form .form-submit');
    $this->dontSeeErrorMessage();
    $this->dontSeeWatchdogPhpErrors();
    $this->webDriverModule->saveSessionSnapshot('user_' . $username);
  }

  /**
   * Login as admin.
   */
  public function loginAsAdmin(): void {
    $this->login($this->config['admin_username'], $this->config['admin_password']);
  }

  /**
   * Logout.
   */
  public function logout($hard = FALSE): void {
    if ($hard) {
      $this->webDriverModule->amOnDrupalPage('/user/logout');
      $this->webDriverModule->deleteSessionSnapshot('user_' . $this->grabCurretUserName());
    }
    else {
      $this->webDriverModule->webDriver->manage()->deleteAllCookies();
    }
  }

  /**
   * Open vertical tab.
   */
  public function openVerticalTab($id): void {
    $id = ltrim($id, '#');
    $this->webDriverModule->scrollTo(['css' => 'a[href="#' . $id . '"]'], 0, -30);
    $this->webDriverModule->click('a[href="#' . $id . '"]');
  }

  /**
   * Open details.
   */
  public function openDetails($details_selector): void {
    $this->webDriverModule->scrollTo(['css' => $details_selector], 0, -30);
    if (!$this->webDriverModule->grabAttributeFrom($details_selector, 'open')) {
      $this->webDriverModule->click($details_selector . ' > summary');
    }
  }

  /**
   * Fill textarea with format
   */
  public function fillTextareaWithFormat(string $wrapper_selector, string $text, string $format = 'raw_html'): void {
    $this->webDriverModule->selectOption($wrapper_selector . ' .filter-list', $format);
    $this->webDriverModule->fillField($wrapper_selector . ' .text-full', $text);
  }

  /**
   * Return last added node id.
   */
  public function grabLastAddedNodeId(string $node_type): int {
    return $this->acceptanceHelperModule->sqlQuery("SELECT MAX(nid) FROM node WHERE type = '$node_type'")->fetchColumn();
  }

  /**
   * Return last added menu item id.
   */
  public function grabLastAddedMenuItemId(): int {
    return $this->acceptanceHelperModule->sqlQuery("SELECT MAX(id) FROM menu_link_content")->fetchColumn();
  }

  /**
   * Return menu item uuid by id.
   */
  public function grabMenuItemUuidById(int $menu_item_id): string {
    return $this->dbModule->grabFromDatabase('menu_link_content', 'uuid', ['id' => $menu_item_id]);
  }

  /**
   * Return last added file id.
   */
  public function grabLastAddedFileId(): int {
    return $this->acceptanceHelperModule->sqlQuery("SELECT MAX(fid) FROM file_managed")->fetchColumn();
  }

  /**
   * Return file info from file_managed table.
   */
  public function grabFileInfoFromDatabase(int $file_id): array {
    return $this->acceptanceHelperModule->sqlQuery("SELECT * FROM file_managed WHERE fid = $file_id")->fetch();
  }

  /**
   * Return current user id.
   */
  public function grabCurrentUserId(): int {
    return $this->webDriverModule->executeJS('return drupalSettings.user.uid;');
  }

  /**
   * Return current user name.
   */
  public function grabCurretUserName(): string {
    return $this->gragUserNameById($this->grabCurrentUserId());
  }

  /**
   * Return user name by user id.
   */
  public function grabUserNameById(int $user_id): string {
    return $this->acceptanceHelperModule->sqlQuery("SELECT name FROM users WHERE uid = $user_id")->fetchColumn();
  }

  /**
   * Return path alias.
   */
  public function grabPathAlias(string $system_path): string {
    $path_alias = $this->acceptanceHelperModule->sqlQuery("SELECT alias FROM path_alias WHERE path = '$system_path'")->fetchColumn();
    return $path_alias ? $path_alias : $system_path;
  }

  /**
   * Test urls.
   */
  public function testUrls(array $urls): void {
    foreach ($urls as $url) {
      $this->amOnDrupalPage($url);
    }
  }

  /**
   * Return codeception module settings.
   */
  private function getEnabledModuleSettings($module_name, array $settings): ?array {
    foreach ($settings['modules']['enabled'] as $enabled) {
      $enabled_module_name = array_key_first($enabled);
      if ($module_name == $enabled_module_name) {
        return $enabled[$enabled_module_name];
      }
    }
  }

}
