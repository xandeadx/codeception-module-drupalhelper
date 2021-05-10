<?php

namespace Codeception\Module;

use Facebook\WebDriver\WebDriverBy;

class AcceptanceHelper extends \Codeception\Module {

  protected $config = [
    'page_title_selector' => '.page-title',
    'breadcrumb_item_selector' => '.breadcrumb__item',
  ];

  protected \Codeception\Module\WebDriver $webDriverModule;

  protected \Codeception\Module\Db $dbModule;

  /**
   * {@inheritDoc}
   */
  public function _initialize(): void {
    $this->webDriverModule = $this->getModule('WebDriver');
    $this->dbModule = $this->getModule('Db');
  }

  /**
   * Sql query.
   *
   * @return \PDOStatement|false
   */
  public function sqlQuery(string $query) {
    return $this->dbModule->_getDbh()->query($query);
  }

  /**
   * See page title.
   */
  public function seePageTitle(string $page_title): void {
    $this->webDriverModule->see($page_title, $this->config['page_title_selector']);
  }

  /**
   * See list.
   */
  public function seeList(array $items, string $item_selector): void {
    foreach ($items as $key => $item) {
      $this->webDriverModule->see($item, $item_selector . ':nth-child(' . ($key + 1) . ')');
    }
  }

  /**
   * See breadcrumb.
   */
  public function seeBreadcrumb(array $items): void {
    $this->seeList($items, $this->config['breadcrumb_item_selector']);
  }

  /**
   * See element attribute exists or see attribute value.
   */
  public function seeElementAttribute(string $element_selector, string $attribute_name, string $attribute_value = NULL): void {
    $this->webDriverModule->seeElementInDOM($element_selector);
    $this->webDriverModule->seeElementInDOM($element_selector . '[' . $attribute_name . ']');
    if ($attribute_value !== NULL) {
      $element_attribute_value = $this->webDriverModule->grabAttributeFrom($element_selector, $attribute_name);
      $this->assertEquals($attribute_value, $element_attribute_value);
    }
  }

  /**
   * See element attribute not exists.
   */
  public function dontSeeElementAttribute(string $element_selector, string $attribute_name): void {
    $this->webDriverModule->seeElementInDOM($element_selector);
    $this->webDriverModule->dontSeeElementInDOM($element_selector . '[' . $attribute_name . ']');
  }

  /**
   * See field by name.
   */
  public function seeField(string $name) {
    $this->webDriverModule->seeElement('[name="' . $name . '"]');
  }

  /**
   * See in DOM field by name.
   */
  public function seeFieldInDom(string $name) {
    $this->webDriverModule->seeElementInDOM('[name="' . $name . '"]');
  }

  /**
   * Fill field using javascript.
   */
  public function fillFieldUsingJs(string $field_name, $value): void {
    $this->webDriverModule->executeJS("document.querySelector('[name=\"$field_name\"]').value = '$value';");
  }

  /**
   * Return max database value.
   */
  public function grabMaxDatabaseValue(string $table, string $column, string $where = null): string {
    $query = "SELECT MAX($column) FROM $table";

    if ($where) {
      $query .= " WHERE $where";
    }

    return $this->sqlQuery($query)->fetchColumn();
  }

  /**
   * Return elements attribute array.
   */
  public function grabElementsAttribute(string $selector, string $attribute_name): array {
    return $this->webDriverModule->grabMultiple($selector, $attribute_name);
  }

  /**
   * Return links hrefs.
   */
  public function grabLinksUrls(string $selector): array {
    $urls = $this->grabElementsAttribute($selector, 'href');
    $urls = array_filter($urls, function ($url) {
      return $url && !preg_match('/^[a-z]+:[^\/]/', $url);
    });
    $urls = array_unique($urls);
    return $urls;
  }

  /**
   * Fill checkbox.
   */
  public function fillCheckbox(string $checkbox, bool $enabled = TRUE): void {
    if ($enabled) {
      $this->webDriverModule->checkOption($checkbox);
    }
    else {
      $this->webDriverModule->uncheckOption($checkbox);
    }
  }

  /**
   * Fill checkboxes.
   */
  public function fillCheckboxes(array $checkboxes, bool $enabled = TRUE): void {
    foreach ($checkboxes as $checkbox) {
      $this->fillCheckbox($checkbox, $enabled);
    }
  }

  /**
   * Click using javascript.
   */
  public function clickUsingJs(string $selector): void {
    $this->webDriverModule->executeJS("document.querySelector('$selector').click();");
  }

  /**
   * Click using jQuery.
   */
  public function clickUsingJquery(string $selector): void {
    $this->webDriverModule->executeJS("jQuery('$selector').click();");
  }

  /**
   * See one element.
   */
  public function seeOneElement(string $selector): void {
    $this->webDriverModule->seeNumberOfElements($selector, 1);
  }

  /**
   * See jQuery Dialog.
   */
  public function seeJqueryDialog(string $title = NULL): void {
    $this->webDriverModule->seeElement('.ui-dialog');
    if ($title) {
      $this->webDriverModule->see($title, '.ui-dialog-title');
    }
  }

  /**
   * Dont see jQuery Dialog.
   */
  public function dontSeeJqueryDialog(): void {
    $this->webDriverModule->dontSeeElement('.ui-dialog');
  }

  /**
   * Wait for jquery dialog.
   */
  public function waitForJqueryDialog(): void {
    $this->webDriverModule->waitForElement('.ui-dialog');
    $this->webDriverModule->wait(0.5); // Wait for animation end
  }

  /**
   * Close jQuery Dialog.
   */
  public function closeJqueryDialog(): void {
    $this->webDriverModule->click('.ui-dialog-titlebar-close');
  }

  /**
   * Instant scroll (without animation).
   */
  public function scrollToWithoutAnimation($selector, $offsetX = 0, $offsetY = 0): void {
    $element = $this->webDriverModule->webDriver->findElement(WebDriverBy::cssSelector($selector));
    $x = $element->getLocation()->getX() + $offsetX;
    $y = $element->getLocation()->getY() + $offsetY;
    $this->webDriverModule->executeJS("window.scrollTo({top: $y, left: $x, behavior: 'instant'})");
  }

  /**
   * Generate unique string.
   */
  public function generateString(string $string, string $function = '', bool $add_time = TRUE): string {
    if ($function) {
      $string .= ' ' . $function;
    }
    if ($add_time) {
      $string .= ' ' . date(DATE_ATOM);
    }

    return $string;
  }

  /**
   * Return test name.
   */
  public function grabTestName(): string {
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10) as $info) {
      if (str_starts_with($info['function'], 'test')) {
        return $info['function'];
      }
    }

    return 'unknown';
  }

  /**
   * Return host with shema, eg "https://example.test"
   */
  public function grabHostWithShema(): string {
    return rtrim($this->webDriverModule->_getUrl(), '/');
  }

  /**
   * Return element inner html.
   */
  public function grabElementInnerHtml($selector): string {
    return $this->webDriverModule->grabAttributeFrom($selector, 'innerHTML');
  }

  /**
   * Return random string.
   */
  public function generateRandomString(int $length = 8): string {
    return substr(str_shuffle(md5(microtime())), 0, $length);
  }

  /**
   * Generate entity label.
   */
  public function generateLabel(string $prefix): string {
    static $counters = [];

    $test_name = $this->grabTestName();
    $counter_name = $test_name . ':' . $prefix;
    $counters[$counter_name] = ($counters[$counter_name] ?? 0) + 1;

    return strtr('@prefix для @test_name', [
      '@prefix' => $prefix . ($counters[$counter_name] > 1 ? ' ' . $counters[$counter_name] : ''),
      '@test_name' => $test_name,
    ]);
  }

  /**
   * Return number of elements.
   */
  public function grabNumberOfElement(string $selector): int {
    return count($this->webDriverModule->grabMultiple($selector));
  }

}
