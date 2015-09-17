<?php
/**
 * Copyright (C) 2015 David Young
 *
 * Defines the path matcher
 */
namespace Opulence\Routing\Routes\Compilers\Matchers;
use Opulence\HTTP\Requests\Request;
use Opulence\Routing\Routes\ParsedRoute;

class PathMatcher implements IRouteMatcher
{
    /**
     * @inheritdoc
     * @param array $matches The list of regex matches
     */
    public function isMatch(ParsedRoute $route, Request $request, array &$matches = [])
    {
        $isMatch = preg_match($route->getPathRegex(), $request->getPath(), $matches) === 1;
        // Remove the subject
        array_shift($matches);

        return $isMatch;
    }
}