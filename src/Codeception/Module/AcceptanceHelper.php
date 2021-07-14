<?php

namespace Codeception\Module;

use Facebook\WebDriver\WebDriverBy;

class AcceptanceHelper extends \Codeception\Module {

  protected $config = [
    'page_title_selector' => '.page-title',
    'breadcrumb_item_selector' => '.breadcrumb__item',
    'sticky_header_height' => 0,
    'devices_size' => [
      'mobile' => [
        'width' => 600,
        'height' => 900,
      ],
    ],
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
  public function scrollToWithoutAnimation(string $selector, int $offsetX = NULL, int $offsetY = NULL): void {
    if ($offsetY === NULL) {
      $offsetY = $this->config['sticky_header_height'] * -1;
    }

    $element = $this->webDriverModule->webDriver->findElement(WebDriverBy::cssSelector($selector));
    $x = $element->getLocation()->getX() + (int)$offsetX;
    $y = $element->getLocation()->getY() + (int)$offsetY;
    $this->webDriverModule->executeJS("window.scrollTo({top: $y, left: $x, behavior: 'instant'})");
  }

  /**
   * Scroll to top.
   */
  public function scrollToTop(): void {
    $this->scrollToWithoutAnimation('body');
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
    return $this->webDriverModule->executeJS("return document.querySelector('$selector').innerHTML");
  }

  /**
   * Return random string.
   */
  public function generateRandomString(int $length = 8): string {
    return substr(str_shuffle(md5(microtime())), 0, $length);
  }

  /**
   * Return random letters.
   */
  public function generateRandomLetters(int $length = 8): string {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, $length);
  }

  /**
   * Generate entity label.
   */
  public function generateLabel(string $prefix, bool $add_random_string = TRUE): string {
    static $counters = [];

    $test_name = $this->grabTestName();
    $counter_name = $test_name . ':' . $prefix;
    $counters[$counter_name] = ($counters[$counter_name] ?? 0) + 1;

    return trim(strtr('@prefix для @test_name @random', [
      '@prefix' => $prefix . ($counters[$counter_name] > 1 ? ' ' . $counters[$counter_name] : ''),
      '@test_name' => $test_name,
      '@random' => $add_random_string ? $this->generateRandomLetters(4) : '',
    ]));
  }

  /**
   * Return number of elements.
   */
  public function grabNumberOfElements(string $selector): int {
    return count($this->webDriverModule->grabMultiple($selector));
  }

  /**
   * Uncheck checkboxes.
   */
  public function uncheckCheckboxes(string $checkboxes_selector): void {
    $checkoxes = $this->webDriverModule->_findElements($checkboxes_selector);
    foreach ($checkoxes as $checkbox) {
      $this->webDriverModule->uncheckOption($checkbox);
    }
  }

  /**
   * Check checkboxes.
   */
  public function checkCheckboxes(string $checkboxes_selector): void {
    $checkoxes = $this->webDriverModule->_findElements($checkboxes_selector);
    foreach ($checkoxes as $checkbox) {
      $this->webDriverModule->checkOption($checkbox);
    }
  }

  /**
   * Uncheck options. Alias for uncheckCheckboxes().
   */
  public function uncheckOptions(string $options_selector): void {
    $this->uncheckCheckboxes($options_selector);
  }

  /**
   * Check options. Alias for checkCheckboxes().
   */
  public function checkOptions(string $options_selector): void {
    $this->checkCheckboxes($options_selector);
  }

  /**
   * See checkboxes is checked.
   *
   * Example:
   * <code>
   * $I->seeCheckboxesIsChecked([1, 2, 3], 'example-input[@value]');
   * </code>
   */
  public function seeCheckboxesIsChecked(array $values, string $checkbox_template): void {
    foreach ($values as $value) {
      $this->webDriverModule->seeCheckboxIsChecked(str_replace('@value', $value, $checkbox_template));
    }
  }

  /**
   * Resize window.
   */
  public function changeDevice(string $device_name): void {
    $device_size = $this->config['devices_size'][$device_name];
    $this->webDriverModule->resizeWindow($device_size['width'], $device_size['height']);
    $this->webDriverModule->wait(0.3);
  }

  /**
   * Return TRUE if element is located in viewport.
   */
  public function grabIsElementInViewport(string $selector): bool {
    return $this->webDriverModule->executeJS("
      var element_rect = document.querySelector('$selector').getBoundingClientRect();
      return (
        element_rect.top >= 0 &&
        element_rect.left >= 0 &&
        element_rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        element_rect.right <= (window.innerWidth || document.documentElement.clientWidth)
      );
    ");
  }

  /**
   * See element in viewport.
   */
  public function seeElementInViewport(string $selector): void {
    $this->assertTrue($this->grabIsElementInViewport($selector), 'Element "' . $selector . '" is not in viewport.');
  }

  /**
   * Dont see element in viewport.
   */
  public function dontSeeElementInViewport(string $selector): void {
    $this->assertFalse($this->grabIsElementInViewport($selector), 'Element "' . $selector . '" in viewport.');
  }

  /**
   * See canonical.
   *
   * @param string $url Relative url without domain
   */
  public function seeCanonical(string $url): void {
    $host_with_schema = $this->grabHostWithShema();
    $this->seeElementAttribute('link[rel="canonical"]', 'href', $host_with_schema . $url);
  }

}
