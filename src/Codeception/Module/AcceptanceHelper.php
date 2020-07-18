<?php

namespace Codeception\Module;

class AcceptanceHelper extends \Codeception\Module {

  protected $config = [
    'page_title_selector' => '.page-title',
    'breadcrumb_item_selector' => '.breadcrumb__item',
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
   * {@inheritDoc}
   */
  public function _initialize() {
    $this->webdriver = $this->getModule('WebDriver');
    $this->db = $this->getModule('Db');
  }

  /**
   * Sql query.
   */
  public function sqlQuery($query){
    return $this->db->_getDbh()->query($query);
  }

  /**
   * See page title.
   */
  public function seePageTitle($page_title) {
    $this->webdriver->see($page_title, ['css' => $this->config['page_title_selector']]);
  }

  /**
   * See list.
   */
  public function seeList(array $items, $item_selector) {
    foreach ($items as $key => $item) {
      $this->webdriver->see($item, ['css' => $item_selector . ':nth-child(' . ($key + 1) . ')']);
    }
  }

  /**
   * See breadcrumb.
   */
  public function seeBreadcrumb(array $items) {
    $this->seeList($items, $this->config['breadcrumb_item_selector']);
  }

  /**
   * Return max database value.
   *
   * @return string
   */
  public function grabMaxDatabaseValue($table, $column, $where = null) {
    $query = "SELECT MAX($column) FROM $table";

    if ($where) {
      $query .= " WHERE $where";
    }

    return $this->sqlQuery($query)->fetchColumn();
  }

}
