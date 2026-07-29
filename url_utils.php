<?php

function getUrlPrefix(string $url): ?string
{
    $parts = parse_url($url);

    if (
        $parts === false ||
        !isset($parts['scheme'], $parts['host'])
    ) {
        return null;
    }

    $result = $parts['scheme'] . '://';

    if (isset($parts['user'])) {
        $result .= $parts['user'];

        if (isset($parts['pass'])) {
            $result .= ':' . $parts['pass'];
        }

        $result .= '@';
    }

    $result .= $parts['host'];

    if (isset($parts['port'])) {
        $result .= ':' . $parts['port'];
    }

    return $result;
}

function isRelativeHref(string $href): bool {
    return filter_var($href, FILTER_VALIDATE_URL) === false;
}

