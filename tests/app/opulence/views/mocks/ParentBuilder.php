<?php
/**
 * Copyright (C) 2015 David Young
 *
 * Mocks a builder that builds a parent for use in testing
 */
namespace Opulence\Tests\Views\Mocks;
use Opulence\Views\IBuilder;
use Opulence\Views\IView;

class ParentBuilder implements IBuilder
{
    /**
     * @inheritdoc
     */
    public function build(IView $view)
    {
        $view->setVar("foo", "blah");
        $view->setVar("bar", true);

        return $view;
    }
}