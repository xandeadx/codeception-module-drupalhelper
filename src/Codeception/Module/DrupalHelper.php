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

  protected \Codeception\Module\WebDriver $webDriverModule;

  protected \Codeception\Module\Db $dbModule;

  protected \Codeception\Module\AcceptanceHelper $acceptanceHelperModule;

  /**
   * {@inheritDoc}
   */
  public function _initialize(): void {
    $this->webDriverModule = $this->getModule('WebDriver');
    $this->dbModule = $this->getModule('Db');
    $this->acceptanceHelperModule = $this->getModule('AcceptanceHelper');
  }

  /**
   * {@inheritDoc}
   */
  public function _beforeSuite($settings = []): void {
    $db_module_settings = $this->getEnabledModuleSettings('Db', $settings);

    if ($this->config['create_dump'] && $db_module_settings['populate']) {
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

      $this->drush('sql-dump --result-file="' . $path_to_dump . '" --structure-tables-list="' . implode(',', $exclude_data_tables) . '"', 'prod');
    }
  }

  /**
   * Run drush command and return result.
   */
  public function drush(string $command, string $environment = 'test'): string {
    $path_to_drush = str_replace('/', DIRECTORY_SEPARATOR, 'vendor/bin/drush');

    if ($environment == 'test') {
      $webdriver_module_url = $this->webDriverModule->_getConfig('url');
      $uri = parse_url($webdriver_module_url, PHP_URL_HOST);
      $command .= ' --uri=' . $uri;
    }

    return exec($path_to_drush . ' ' . $command);
  }

  /**
   * Run cron.
   */
  public function runCron(): void {
    $this->loginAsAdmin();
    $this->amOnDrupalPage('/admin/config/system/cron');
    $this->webDriverModule->click('#edit-run');
    $this->dontSeeWatchdogPhpErrors();
    $this->dontSeeErrorMessage();
  }

  /**
   * Goto to drupal page and check errors.
   */
  public function amOnDrupalPage(string $url): void {
    if (str_contains($url, '://')) {
      $this->webDriverModule->amOnUrl($url);
    }
    else {
      $this->webDriverModule->amOnPage($url);
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
    $errors_count = (int)$this->acceptanceHelperModule->sqlQuery("
      SELECT COUNT(*)
      FROM watchdog
      WHERE
        type = 'php' AND
        variables NOT LIKE '%rename(%/php/twig/%'
    ")->fetchColumn();
    $this->assertEquals(0, $errors_count);
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
  public function logout(bool $delete_session = FALSE): void {
    if ($delete_session) {
      $this->amOnDrupalPage('/user/logout');
      $this->webDriverModule->deleteSessionSnapshot('user_' . $this->grabCurretUserName());
    }
    else {
      $this->webDriverModule->webDriver->manage()->deleteAllCookies();
    }
  }

  /**
   * Open vertical tab.
   */
  public function openVerticalTab(string $id): void {
    $id = ltrim($id, '#');
    $this->webDriverModule->scrollTo(['css' => 'a[href="#' . $id . '"]'], 0, -30);
    $this->webDriverModule->click('a[href="#' . $id . '"]');
  }

  /**
   * Open details.
   */
  public function openDetails(string $details_selector): void {
    $this->webDriverModule->scrollTo(['css' => $details_selector], 0, -30);
    if (!$this->webDriverModule->grabAttributeFrom($details_selector, 'open')) {
      $this->webDriverModule->click($details_selector . ' > summary');
    }
  }

  /**
   * Fill textarea with format
   */
  public function fillTextareaWithFormat(string $wrapper_selector, string $text, string $format = 'raw_html'): void {
    $this->webDriverModule->selectOption($wrapper_selector . ' .form-select', $format);
    $this->webDriverModule->fillField($wrapper_selector . ' .text-full', $text);
  }

  /**
   * Return last added node id.
   */
  public function grabLastAddedNodeId(string $node_type): int {
    return (int)$this->acceptanceHelperModule->sqlQuery("
      SELECT MAX(nid)
      FROM node
      WHERE type = '$node_type'
    ")->fetchColumn();
  }

  /**
   * Return last added term id.
   */
  public function grabLastAddedTermId(string $vocabulary_name): int {
    return (int)$this->acceptanceHelperModule->sqlQuery("
      SELECT MAX(tid)
      FROM taxonomy_term_data
      WHERE vid = '$vocabulary_name'
    ")->fetchColumn();
  }

  /**
   * Return term id by name.
   */
  public function grabTermIdByName(string $vocabulary_name, string $term_name): int {
    return (int)$this->acceptanceHelperModule->sqlQuery("
      SELECT MAX(tid)
      FROM taxonomy_term_field_data
      WHERE vid = '$vocabulary_name' AND name = '$term_name'
    ")->fetchColumn();
  }

  /**
   * Return term id by name.
   */
  public function grabTermNameById(string $vocabulary_name, string $term_name): string {
    return $this->acceptanceHelperModule->sqlQuery("
      SELECT name
      FROM taxonomy_term_field_data
      WHERE vid = '$vocabulary_name' AND name = '$term_name'
    ")->fetchColumn();
  }

  /**
   * Create term and return term id.
   */
  public function createTerm(string $vocabulary_name, string $term_name, bool $force = FALSE): int {
    if (!$force && ($term_id = $this->grabTermIdByName($vocabulary_name, $term_name))) {
      return $term_id;
    }

    $this->loginAsAdmin();
    $this->amOnDrupalPage('/admin/structure/taxonomy/manage/' . $vocabulary_name . '/add');
    $this->webDriverModule->fillField('name[0][value]', $term_name);
    $this->webDriverModule->click('.form-actions .form-submit');
    $this->dontSeeErrorMessage();
    $this->dontSeeWatchdogPhpErrors();

    return $this->grabLastAddedTermId($vocabulary_name);
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
   *
   * @param string $system_path System path starting with "/", eg "/node/123"
   */
  public function grabPathAlias(string $system_path): string {
    $path_alias = $this->acceptanceHelperModule->sqlQuery("SELECT alias FROM path_alias WHERE path = '$system_path'")->fetchColumn();
    return $path_alias ? $path_alias : $system_path;
  }

  /**
   * Open each URL and check page for Drupal errors.
   */
  public function testDrupalPages(array $urls): void {
    foreach ($urls as $url) {
      $this->amOnDrupalPage($url);
    }
  }

  /**
   * Truncate table.
   */
  public function truncateTable(string $table): void {
    $this->acceptanceHelperModule->sqlQuery("TRUNCATE TABLE $table");
  }

  /**
   * Clear cache table.
   */
  public function clearCacheTable(string $bin): void {
    $this->truncateTable('cache_' . $bin);
  }

  /**
   * Clear flood table.
   */
  public function clearFloodTable(): void {
    $this->truncateTable('flood');
  }

  /**
   * Return codeception module settings.
   */
  private function getEnabledModuleSettings(string $module_name, array $settings): ?array {
    foreach ($settings['modules']['enabled'] as $enabled) {
      $enabled_module_name = array_key_first($enabled);
      if ($module_name == $enabled_module_name) {
        return $enabled[$enabled_module_name];
      }
    }
  }

}
