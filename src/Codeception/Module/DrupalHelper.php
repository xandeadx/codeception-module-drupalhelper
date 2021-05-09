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

  protected string $currentUsername = '';

  protected array $rememberedUsername = [];

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

      $this->runDrush('sql-dump --result-file="' . $path_to_dump . '" --structure-tables-list="' . implode(',', $exclude_data_tables) . '"', 'prod');
    }

    if ($this->tableExist('watchdog')) {
      $this->truncateTable('watchdog');
    }
  }

  /**
   * Run drush command and return result.
   */
  public function runDrush(string $command, string $environment = 'test'): string {
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
    $this->rememberCurrentSession();
    $this->loginAsAdmin();
    $this->amOnDrupalPage('/admin/config/system/cron');
    $this->webDriverModule->click('#edit-run');
    $this->dontSeeDrupalErrors();
    $this->restoreRememberedSession();
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
    $this->dontSeeDrupalErrors();
  }

  /**
   * Goto node page.
   */
  public function amOnNodePage(int $nid): void {
    $this->amOnDrupalPage($this->grabNodeAlias($nid));
  }

  /**
   * Goto term page.
   */
  public function amOnTermPage(int $term_id): void {
    $this->amOnDrupalPage($this->grabTermAlias($term_id));
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
    $this->assertEquals(0, $errors_count, 'Watchdog contains php errors.');
  }

  /**
   * Dont see flash errors and watchdog errors.
   */
  public function dontSeeDrupalErrors(): void {
    $this->dontSeeErrorMessage();
    $this->dontSeeWatchdogPhpErrors();
  }

  /**
   * Login as $username.
   */
  public function login(string $username, string $password): void {
    $this->currentUsername = $username;

    if ($this->webDriverModule->loadSessionSnapshot('user_' . $username)) {
      return;
    }

    $this->amOnDrupalPage('/user/login');
    $this->webDriverModule->fillField('.user-login-form input[name="name"]', $username);
    $this->webDriverModule->fillField('.user-login-form input[name="pass"]', $password);
    $this->webDriverModule->click('.user-login-form .form-submit');
    $this->dontSeeDrupalErrors();
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
    $this->currentUsername = '';
  }

  /**
   * Return current username.
   */
  public function grabCurrentUsername(): string {
    return $this->currentUsername;
  }

  /**
   * Remember current user.
   */
  public function rememberCurrentSession(): void {
    $this->rememberedUsername[] = $this->currentUsername;
  }

  /**
   * Restore remembered user session.
   */
  public function restoreRememberedSession(): void {
    $remembered_username = array_pop($this->rememberedUsername);
    if ($this->currentUsername != $remembered_username) {
      if ($remembered_username) {
        $this->webDriverModule->loadSessionSnapshot('user_' . $remembered_username);
      }
      else {
        $this->logout();
      }
      $this->currentUsername = $remembered_username;
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
    $this->acceptanceHelperModule->scrollToWithoutAnimation($details_selector, 0, -30);
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
  public function grabLastAddedNodeId(string $node_type = NULL): int {
    return (int)$this->acceptanceHelperModule->sqlQuery("
      SELECT MAX(nid)
      FROM node
      " . ($node_type ? "WHERE type = '$node_type'" : "") . "
    ")->fetchColumn();
  }

  /**
   * Delete node.
   */
  public function deleteNode(int $nid, bool $check = TRUE): void {
    $this->rememberCurrentSession();
    $this->loginAsAdmin();
    $this->amOnDrupalPage("/node/$nid/delete");
    $this->webDriverModule->click('.form-submit');

    if ($check) {
      $this->dontSeeDrupalErrors();
      $this->dbModule->dontSeeInDatabase('node', ['nid' => $nid]);
    }

    $this->restoreRememberedSession();
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
  public function grabTermNameById(string $term_id): string {
    return $this->acceptanceHelperModule->sqlQuery("
      SELECT name
      FROM taxonomy_term_field_data
      WHERE tid = '$term_id'
    ")->fetchColumn();
  }

  /**
   * Create term and return term id.
   */
  public function createTerm(string $vocabulary_name, string $term_name = NULL, bool $force = FALSE): int {
    if (!$force && ($term_id = $this->grabTermIdByName($vocabulary_name, $term_name))) {
      return $term_id;
    }

    $this->rememberCurrentSession();
    $this->loginAsAdmin();
    $this->amOnDrupalPage('/admin/structure/taxonomy/manage/' . $vocabulary_name . '/add');
    $this->webDriverModule->fillField('name[0][value]', $term_name ?? 'Термин для ' . $this->acceptanceHelperModule->grabTestName());
    $this->webDriverModule->click('.form-actions .form-submit');
    $this->dontSeeDrupalErrors();
    $this->restoreRememberedSession();

    return $this->grabLastAddedTermId($vocabulary_name);
  }

  /**
   * Delete term.
   */
  public function deleteTerm(int $term_id, bool $check = TRUE): void {
    $this->rememberCurrentSession();
    $this->loginAsAdmin();
    $this->amOnDrupalPage('/taxonomy/term/' . $term_id . '/delete');
    $this->webDriverModule->click('.form-submit');

    if ($check) {
      $this->dontSeeDrupalErrors();
      $this->dbModule->dontSeeInDatabase('taxonomy_term_data', ['tid' => $term_id]);
    }

    $this->restoreRememberedSession();
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
   * Delete menu item.
   */
  public function deleteMenuItem(int $menu_item_id): void {
    $this->rememberCurrentSession();
    $this->loginAsAdmin();
    $this->amOnDrupalPage('/admin/structure/menu/item/' . $menu_item_id . '/delete');
    $this->webDriverModule->click('.form-actions .form-submit');
    $this->dontSeeDrupalErrors();
    $this->restoreRememberedSession();
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
   * Return last added comment id.
   */
  public function grabLastAddedCommentId(): int {
    return $this->acceptanceHelperModule->sqlQuery("SELECT MAX(cid) FROM comment")->fetchColumn();
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
   * Return node alias.
   */
  public function grabNodeAlias(int $node_id): string {
    return $this->grabPathAlias('/node/' . $node_id);
  }

  /**
   * Return term alias.
   */
  public function grabTermAlias(int $term_id): string {
    return $this->grabPathAlias('/taxonomy/term/' . $term_id);
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
   * Return TRUE if table exists.
   */
  public function tableExist(string $table_name): bool {
    try {
      $this->acceptanceHelperModule->sqlQuery("SELECT 1 FROM $table_name");
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Truncate table.
   */
  public function truncateTable(string $table): void {
    $this->acceptanceHelperModule->sqlQuery("TRUNCATE TABLE $table");
  }

  /**
   * Clear table. Alias for DrupalHelper::truncateTable().
   */
  public function clearTable(string $table): void {
    $this->truncateTable($table);
  }

  /**
   * Clear cache table.
   */
  public function clearCacheTable(string $bin): void {
    $this->clearTable('cache_' . $bin);
  }

  /**
   * Clear render cache.
   */
  public function clearRenderCache(): void {
    $this->clearCacheTable('render');
    $this->clearCacheTable('page');
    $this->clearTable('cachetags');
  }

  /**
   * Clear flood table.
   */
  public function clearFloodTable(): void {
    $this->truncateTable('flood');
  }

  /**
   * Clear all caches.
   */
  public function clearAllCaches(): void {
    $this->rememberCurrentSession();
    $this->loginAsAdmin();
    $this->amOnDrupalPage('/admin/config/development/performance');
    $this->webDriverModule->click('#edit-clear');
    $this->dontSeeDrupalErrors();
    $this->restoreRememberedSession();
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
