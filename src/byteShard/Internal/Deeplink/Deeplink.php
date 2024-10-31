<?php

namespace byteShard\Internal\Deeplink;

use byteShard\ID\ID;
use byteShard\ID\TabIDElement;
use byteShard\Internal\Config;
use byteShard\Internal\Server;
use byteShard\Session;

class Deeplink
{

    private const COOKIE = 'deeplink';

    public static function checkReferrer(): void
    {
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referrer      = $_SERVER['HTTP_REFERER'];
            $urlComponents = parse_url($referrer);

            if (isset($urlComponents['query'])) {
                parse_str($urlComponents['query'], $params);
                if (!empty($params)) {
                    self::setCookie(self::sanitizeParameters($params));
                }
            }
        }
    }

    /**
     * @param array<string, string|array<mixed>> $params
     * @return array{
     *   tab?: string,
     *   cell?: string,
     *   column?: string,
     *   filter?: array<string, string>
     * }
     */
    private static function sanitizeParameters(array $params): array
    {
        $result = [];
        foreach ($params as $key => $value) {
            match ($key) {
                // list of allowed keys
                // Replace all non classname characters and reduce multiple backslashes to one
                'tab', 'cell', 'column' => $result[$key] = preg_replace('/\\\\+/', '\\', preg_replace('/[^a-zA-Z0-9_\\\\]/', '', $value)),
                'filter'                => $result[$key] = is_array($value) ? self::sanitizeFilterArray($value) : htmlspecialchars($value),
                default                 => null
            };
        }
        if (isset($result['column'], $result['filter']) && !is_array($result['filter'])) {
            $result['filter'] = [
                $result['column'] => $result['filter']
            ];
            unset($result['column']);
        }
        return $result;
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, string>
     */
    private static function sanitizeFilterArray(array $filters): array
    {
        $result = [];
        foreach ($filters as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            $sanitizedKey          = preg_replace('/\\\\+/', '\\', preg_replace('/[^a-zA-Z0-9_\\\\]/', '', $key));
            $result[$sanitizedKey] = htmlspecialchars($value);
        }
        return $result;
    }

    public static function selectTab(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $endpoint = trim($_SERVER['SCRIPT_NAME'], "/ \n\r\t\v\0");
            if (class_exists('\\config')) {
                /** @noinspection PhpUndefinedClassInspection */
                $config = new \config();
                if (!empty($config->getUrlContext())) {
                    $endpoint = trim(str_replace($config->getUrlContext(), '', $endpoint), "/ \n\r\t\v\0");
                }
            }
            $internalEndpoints = Config::getInternalEndpoints();
            if (in_array($endpoint, $internalEndpoints, true)) {
                return;
            }
            $getParams = !empty($_GET) ? $_GET : self::getCookie();
            $getParams = self::sanitizeParameters($getParams);
            if (!empty($getParams)) {
                if (isset($getParams['tab'])) {
                    Session::setSelectedTab(ID::factory(new TabIDElement($getParams['tab'])));
                    unset($getParams['tab']);
                    self::setCookie($getParams);
                }
            }
            if (!empty($_GET)) {
                header('Location: '.Server::getBaseUrl());
            }
        }
    }

    /**
     * @return array<mixed>|null
     */
    public static function getCookie(): ?array
    {
        if (array_key_exists(self::COOKIE, $_COOKIE)) {
            return json_decode($_COOKIE[self::COOKIE], true);
        }
        return [];
    }

    public static function cleanupCookie(): void
    {
        setcookie(self::COOKIE, '', [
            'expires'  => time() - 3600,
            'secure'   => true,
            'httponly' => true,
            'path'     => '/',
            'sameSite' => 'Strict'
        ]);
        unset($_COOKIE[self::COOKIE]);
    }

    /**
     * @param array<mixed> $params
     * @return void
     */
    private static function setCookie(array $params): void
    {
        if (empty($params)) {
            self::cleanupCookie();
        } else {
            setcookie(self::COOKIE, json_encode($params), [
                'expires'  => 0,
                'secure'   => true,
                'httponly' => true,
                'path'     => '/',
                'sameSite' => 'Strict'
            ]);
        }
    }

    /**
     * @return array{
     *   tab?: string,
     *   cell?: string,
     *   column?: string,
     *   filter?: array<string, string>
     * }
     */
    public static function getPassThroughParameters(): array
    {
        $getParams = $_GET;
        if (is_array($getParams)) {
            return self::sanitizeParameters($getParams);
        }
        return [];
    }
}