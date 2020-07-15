<?php

namespace Codeception\Module;

use Codeception\Module as CodeceptionModule;

class DrupalHelper extends CodeceptionModule {

  public function login($username, $password) {
    $I = $this->getModule('WebDriver');
    $I->amOnPage('/user');
    $I->fillField('#edit-name', $username);
    $I->fillField('#edit-pass', $password);
    $I->click('#edit-submit');
  }

}
