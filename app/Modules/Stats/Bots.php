<?php

namespace App\Modules\Stats;

/**
 * Whether a User-Agent is a program rather than a person (PLAN.md D-051).
 *
 * A pattern list, as every counter without a script on the page has to use: a crawler that
 * says what it is, a link preview, a monitoring service, a command-line tool or a headless
 * browser. One that lies about itself is counted, and nothing on the server can tell it
 * from a person. An empty User-Agent is a program: every browser sends one.
 */
final class Bots
{
    /**
     * Matched case-insensitively anywhere in the string. `bot`, `crawl`, `spider` and
     * `fetch` cover most crawlers by themselves; the rest are the ones that do not use
     * those words. WhatsApp, Telegram and the other messengers are their link previews;
     * a page opened INSIDE an app (Instagram, Facebook, Line) is a person and not listed.
     */
    private const PATTERN = '~bot|crawl|spider|slurp|fetch|scrap|preview|headless|lighthouse|pagespeed'
        . '|facebookexternalhit|meta-external|embedly|quora link|outbrain|pinterest|vkshare|w3c_validator'
        . '|whatsapp|telegram|discord|slack|skype|viber'
        . '|curl|wget|python|java/|go-http|okhttp|axios|node|undici|libwww|perl|ruby|php/|httpclient|http_request|guzzle'
        . '|phantom|puppeteer|playwright|selenium|webdriver'
        . '|pingdom|uptime|monitor|statuscake|site24x7|nagios|zabbix|check_http'
        . '|ahrefs|semrush|mj12|majestic|dataforseo|serpstat|screaming frog|sitebulb|seokicks'
        . '|gptbot|chatgpt|claude|anthropic|perplexity|ccbot|bytespider|petalbot|yandex|baidu|sogou|exabot|ia_archiver|archive\.org'
        . '|mediapartners|adsbot|feedburner|feedly|newsblur|inoreader|rss~i';

    public static function is(string $userAgent): bool
    {
        return trim($userAgent) === '' || preg_match(self::PATTERN, $userAgent) === 1;
    }
}
