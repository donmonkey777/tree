<?php

class AbstractDbTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        parent::_before();

        /** @var \Codeception\Module\Cli $cli */
        $cli = $this->getModule("Cli");
        $cli->runShellCommand("vendor\\bin\\phinx rollback -e testing -t 0");
        $cli->runShellCommand("vendor\\bin\\phinx migrate -e testing");
        $cli->runShellCommand("vendor\\bin\\phinx seed:run -e testing");
    }

    protected function _after()
    {
        parent::_after();
    }
}