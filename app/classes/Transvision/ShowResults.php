<?php
namespace Transvision;

/**
 * ShowResults class
 *
 * This class is used to get search results and display them
 *
 * @package Transvision
 */
class ShowResults
{
    /**
     * Create an array for search results with this format:
     * 'entity' => ['string_locale 1', 'string_locale 2']
     * @param array $entities haystack of entities to search in
     * @param array $array_strings strings to look for
     * @return array array of entities as keys and translations as values
     */
    public static function getTMXResults($entities, $array_strings)
    {
        $search_results = [];

        foreach ($entities as $entity) {

            $temp = [];

            foreach ($array_strings as $strings) {
                $temp[] = self::getStringFromEntity($entity, $strings);
            }

            $search_results[$entity] = $temp;
        }

        return $search_results;
    }

    /**
     * Returns the string from its entity or false
     *
     * @param string $entity Entity we are looking for
     * @param array $strings Haystack of strings to search in
     * @return string The string for the entity or false if no matching result
     */
    public static function getStringFromEntity($entity, $strings)
    {
        return isset($strings[$entity]) && $strings[$entity] != ''
               ? $strings[$entity]
               : false;
    }

    /**
     * Nicely format entities for tables by splitting them in subpaths and styling them
     *
     * @param string $entity
     * @param boolean $highlight Optional. Default to false. Use a highlighting style
     * @return string Entity reformated with html markup and css classes for styling
     */
    public static function formatEntity($entity, $highlight = false)
    {
        // let's analyse the entity for the search string
        $chunk  = explode(':', $entity);

        if ($highlight) {
            $entity = array_pop($chunk);
            $highlight = preg_quote($highlight, '/');
            $entity = preg_replace("/($highlight)/i", '<span class="highlight">$1</span>', $entity);
            $entity = '<span class="red">' . $entity . '</span>';
        } else {
            $entity = '<span class="red">' . array_pop($chunk) . '</span>';
        }
        // let's analyse the entity for the search string
        $chunk = explode('/', $chunk[0]);
        $repo  = '<span class="green">' . array_shift($chunk) . '</span>';

        $path = implode('<span class="superset">&nbsp;&sup;&nbsp;</span>', $chunk);

        return $repo . '<span class="superset">&nbsp;&sup;&nbsp;</span>' . $path . '<br>' .$entity;
    }

    /**
     * Hightlight specific elements in the string for locales.
     * Can also highlight specific per locale sub-strings.
     * For example in French non-breaking spaces used in typography
     *
     * @param string $string Source text
     * @param string $locale Optional. Locale code. Defaults to French.
     * @return string Same string with specific sub-strings in span elements
     *                for styling with CSS (.hightlight-gray class)
     */
    public static function highlight($string, $locale = 'fr')
    {
        $replacements = array(
            ' ' => '<span class="highlight-gray"> </span>',
            '…' => '<span class="highlight-gray">…</span>',
        );

        switch ($locale) {
            case 'fr':
            default:
                $replacements['&hellip;'] = '<span class="highlight-gray">…</span>'; // right ellipsis highlight
                break;
        }

        return Strings::multipleStringReplace($replacements, $string);
    }

    /**
     * Html table of search results used by the main view (needs a lot of refactoring)
     *
     * @param array $search_results list of rows
     * @param string $recherche The words searched for
     * @param string $locale1 Reference locale to search in, usually en-US
     * @param string $locale2 Target locale to search in
     * @param array $search_options All the search options from the query
     *
     * @return string html table to insert in the view
     */
    public static function resultsTable($search_results, $recherche, $locale1, $locale2, $search_options)
    {
        $direction1 = RTLSupport::getDirection($locale1);
        $direction2 = RTLSupport::getDirection($locale2);

        if (isset($search_options['extra_locale'])) {
            $locale3    = $search_options['extra_locale'];
            $direction3 = RTLSupport::getDirection($locale3);
            $extra_column_header = "<th>{$locale3}</th>";
        } else {
            $extra_column_header = '';
        }

        $table  = "<table class='collapsable'>
                      <tr>
                        <th>Entity</th>
                        <th>{$locale1}</th>
                        <th>{$locale2}</th>
                        {$extra_column_header}
                      </tr>";


        if (!$search_options['whole_word'] && !$search_options['perfect_match']) {
            $recherche = Utils::uniqueWords($recherche);
        } else {
            $recherche = array($recherche);
        }

        foreach ($search_results as $key => $strings) {

            // Don't highlight search matches in entities when searching strings
            if ($search_options['search_type'] == 'strings') {
                $result_entity = ShowResults::formatEntity($key);
            } else {
                $result_entity = ShowResults::formatEntity($key, $recherche[0]);
            }

            $source_string = trim($strings[0]);
            $target_string = trim($strings[1]);
            $entity_link = "?sourcelocale={$locale1}"
            . "&locale={$locale2}"
            . "&repo={$search_options['repo']}"
            . "&search_type=entities&recherche={$key}";

            $bz_link = [Bugzilla::reportErrorLink(
                $locale2, $key, $source_string, $target_string, $search_options['repo'], $entity_link
            )];

            if (isset($search_options['extra_locale'])) {
                $target_string2 = trim($strings[2]);
                $entity_link = "?sourcelocale={$locale1}"
                                . "&locale={$search_options['extra_locale']}"
                                . "&repo={$search_options['repo']}"
                                . "&search_type=entities&recherche={$key}";
                $bz_link[] = Bugzilla::reportErrorLink(
                    $search_options['extra_locale'], $key, $source_string, $target_string2, $search_options['repo'], $entity_link
                );
            } else {
                $target_string2 = '';
            }

            foreach ($recherche as $val) {
                $source_string = Utils::markString($val, $source_string);
                $target_string = Utils::markString($val, $target_string);
                if (isset($search_options["extra_locale"])) {
                    $target_string2 = Utils::markString($val, $target_string2);
                }
            }

            $source_string = Utils::highlightString($source_string);
            $target_string = Utils::highlightString($target_string);

            if (isset($search_options["extra_locale"])) {
                $target_string2 = Utils::highlightString($target_string2);
            }

            $replacements = array(
                ' '        => '<span class="highlight-gray" title="Non breakable space"> </span>', // nbsp highlight
                ' '        => '<span class="highlight-red" title="Thin space"> </span>', // thin space highlight
                '…'        => '<span class="highlight-gray">…</span>', // right ellipsis highlight
                '&hellip;' => '<span class="highlight-gray">…</span>', // right ellipsis highlight
            );

            $target_string = Strings::multipleStringReplace($replacements, $target_string);

            $temp = explode('-', $locale1);
            $locale1_short_code = $temp[0];

            $temp = explode('-', $locale2);
            $locale2_short_code = $temp[0];

            if ($search_options['repo'] == 'mozilla_org') {
                $locale1_path = VersionControl::svnPath($locale1, $search_options['repo'], $key);
                $locale2_path = VersionControl::svnPath($locale2, $search_options['repo'], $key);
            } else {
                $locale1_path = VersionControl::hgPath($locale1, $search_options['repo'], $key);
                $locale2_path = VersionControl::hgPath($locale2, $search_options['repo'], $key);
            }
            // errors
            $error_message = '';

            // check for final dot
            if (substr(strip_tags($source_string), -1) == '.'
                && substr(strip_tags($target_string), -1) != '.') {
                $error_message = '<em class="error"> No final dot?</em>';
            }

            // check abnormal string length
            $length_diff = Utils::checkAbnormalStringLength($source_string, $target_string);
            if ($length_diff) {
                switch ($length_diff) {
                    case 'small':
                        $error_message = $error_message . '<em class="error"> Small string?</em>';
                        break;
                    case 'large':
                        $error_message = $error_message . '<em class="error"> Large String?</em>';
                        break;
                }
            }

            // Missing string error
            if (!$source_string) {
                $source_string = '<em class="error">warning: missing string</em>';
                $error_message = '';
            }
            if (!$target_string) {
                $target_string = '<em class="error">warning: missing string</em>';
                $error_message = '';
            }
            if (!$target_string2) {
                $target_string2 = '<em class="error">warning: missing string</em>';
                $error_message = '';
            }

            // Replace / and : in the key name and use it as an anchor name
            $anchor_name = str_replace(array('/', ':'), '_', $key);

            // 3locales view
            if (isset($search_options["extra_locale"])) {

                if ($search_options['repo'] == 'mozilla_org') {
                    $locale3_path = VersionControl::svnPath($locale3, $search_options['repo'], $key);
                } else {
                    $locale3_path = VersionControl::hgPath($locale3, $search_options['repo'], $key);
                }



                $extra_column_rows = "
                <td dir='{$direction3}'>
                    <span class='celltitle'>{$locale3}</span>
                    <div class='string'>{$target_string2}</div>
                    <div dir='ltr' class='infos'>
                      <a class='source_link' href='{$locale3_path}'>
                        &lt;source&gt;
                      </a>
                      &nbsp;
                      <a class='bug_link' target='_blank' href='{$bz_link[1]}'>
                        &lt;report a bug&gt;
                      </a>
                    </div>
                  </td>
                </tr>";

            } else {
                $extra_column_rows = '';
            }

            $table .= "
                <tr>
                  <td>
                    <span class='celltitle'>Entity</span>
                    <a class='resultpermalink tag' id='{$anchor_name}' href='#{$anchor_name}' title='Permalink to this result'>link</a>
                    <a class='l10n tag' href='/string/?entity={$key}&amp;repo={$search_options['repo']}' title='List all translations for this entity'>l10n</a>
                    <a class='linktoentity' href=\"/{$entity_link}\">{$result_entity}</a>
                  </td>
                  <td dir='{$direction1}'>
                    <span class='celltitle'>{$locale1}</span>
                    <div class='string'>
                      {$source_string}
                    </div>
                    <div dir='ltr' class='infos'>
                      <a class='source_link' href='{$locale1_path}'>
                        &lt;source&gt;
                      </a>
                      <span>Translate with:</span>
                      <a href='http://translate.google.com/#{$locale1_short_code}/{$locale2_short_code}/"
                      . urlencode(strip_tags($source_string))
                      . "' target='_blank'>Google</a>
                      <a href='http://www.bing.com/translator/?from={$locale1_short_code}&to={$locale2_short_code}&text="
                      . urlencode(strip_tags($source_string))
                      . "' target='_blank'>BING</a>
                    </div>
                  </td>

                  <td dir='{$direction2}'>
                    <span class='celltitle'>{$locale2}</span>
                    <div class='string'>{$target_string}</div>
                    <div dir='ltr' class='infos'>
                      <a class='source_link' href='{$locale2_path}'>
                        &lt;source&gt;
                      </a>
                      &nbsp;
                      <a class='bug_link' target='_blank' href='{$bz_link[0]}'>
                        &lt;report a bug&gt;
                      </a>
                      {$error_message}
                    </div>
                  </td>
                {$extra_column_rows}
                </tr>";
        }

        $table .= "  </table>";

        return $table;
    }
}
