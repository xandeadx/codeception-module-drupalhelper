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
  protected $webdriver;

  /**
   * @var \Codeception\Module\Db
   */
  protected $db;

  /**
   * @var \Codeception\Module\AcceptanceHelper
   */
  protected $acceptancehelper;

  /**
   * {@inheritDoc}
   */
  public function _initialize() {
    $this->webdriver = $this->getModule('WebDriver');
    $this->db = $this->getModule('Db');
    $this->acceptancehelper = $this->getModule('AcceptanceHelper');
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
      $this->webdriver->amOnPage($url);
    }
    else {
      $this->webdriver->amOnUrl($url);
    }
    $this->webdriver->seeElementInDOM('body');
    $this->dontSeeErrorMessage();
    $this->dontSeeWatchdogPhpErrors();
  }

  /**
   * Dont see error message.
   */
  public function dontSeeErrorMessage(): void {
    foreach ($this->config['error_message_selectors'] as $error_message_selector) {
      $this->webdriver->dontSeeElement($error_message_selector);
    }
  }

  /**
   * Dont see watchdog num errors.
   */
  public function dontSeeWatchdogPhpErrors(): void {
    $this->db->seeNumRecords(0, 'watchdog', ['type' => 'php']);
  }

  /**
   * Login as $username.
   */
  public function login(string $username, string $password): void {
    $this->amOnDrupalPage('/user/login');
    $this->webdriver->fillField('.user-login-form input[name="name"]', $username);
    $this->webdriver->fillField('.user-login-form input[name="pass"]', $password);
    $this->webdriver->click('.user-login-form .form-submit');
    $this->dontSeeErrorMessage();
    $this->dontSeeWatchdogPhpErrors();
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
  public function logout(): void {
    $this->amOnDrupalPage('/user/logout');
  }

  /**
   * Open vertical tab.
   */
  public function openVerticalTab($id): void {
    $id = ltrim($id, '#');
    $this->webdriver->scrollTo(['css' => 'a[href="#' . $id . '"]'], 0, -30);
    $this->webdriver->click('a[href="#' . $id . '"]');
  }

  /**
   * Open details.
   */
  public function openDetails($details_selector): void {
    $this->webdriver->scrollTo(['css' => $details_selector], 0, -30);
    if (!$this->webdriver->grabAttributeFrom($details_selector, 'open')) {
      $this->webdriver->click($details_selector . ' > summary');
    }
  }

  /**
   * Fill textarea with format
   */
  public function fillTextareaWithFormat(string $wrapper_selector, string $text, string $format = 'raw_html'): void {
    $this->webdriver->selectOption($wrapper_selector . ' .filter-list', $format);
    $this->webdriver->fillField($wrapper_selector . ' .text-full', $text);
  }

  /**
   * Return last added node id.
   */
  public function grabLastAddedNodeId(string $node_type): int {
    return $this->acceptancehelper->sqlQuery("SELECT MAX(nid) FROM node WHERE type = '$node_type'")->fetchColumn();
  }

  /**
   * Return last added menu item id.
   */
  public function grabLastAddedMenuItemId(): int {
    return $this->acceptancehelper->sqlQuery("SELECT MAX(id) FROM menu_link_content")->fetchColumn();
  }

  /**
   * Return menu item uuid by id.
   */
  public function grabMenuItemUuidById(int $menu_item_id): string {
    return $this->db->grabFromDatabase('menu_link_content', 'uuid', ['id' => $menu_item_id]);
  }

  /**
   * Return last added file id.
   */
  public function grabLastAddedFileId(): int {
    return $this->acceptancehelper->sqlQuery("SELECT MAX(fid) FROM file_managed")->fetchColumn();
  }

  /**
   * Return file info from file_managed table.
   */
  public function grabFileInfoFromDatabase(int $file_id): array {
    return $this->acceptancehelper->sqlQuery("SELECT * FROM file_managed WHERE fid = $file_id")->fetch();
  }

  /**
   * Return current user id.
   */
  public function grabCurrentUserId(): int {
    return $this->webdriver->executeJS('return drupalSettings.user.uid;');
  }

  /**
   * Return path alias.
   */
  public function grabPathAlias(string $system_path): string {
    $path_alias = $this->acceptancehelper->sqlQuery("SELECT alias FROM path_alias WHERE path = '$system_path'")->fetchColumn();
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
