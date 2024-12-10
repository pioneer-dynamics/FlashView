<?php

namespace App\Http\Controllers;

use App\Exceptions\MarkdownExportOfUnApprovedConfiguration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Handles HTTP requests related to displaying a markdown document.
 */
class MarkdownDocumentController extends Controller
{
    /**
     * @var array Configurations that are allowed to be exported (in dot format)
     *
     * <code>
     * <?php
     *  $allowed_configurations = [
     *      'app.domain',
     *      'app.name',
     *  ]
     * ?>
     * </code>
     */
    private $allowed_configurations = [
        'app.domain',
        'app.name',
        'support.legal',
    ];

    /**
     * Get the date the file was last updated on
     *
     * @param  string  $file
     * @return string $date in the format d-M-Y
     *
     * @example ./MarkdownDocumentController.php How to use this function.
     */
    private function getFileUpdatedDate($file)
    {
        $last_modified = File::lastModified(resource_path($file));

        return Carbon::createFromFormat('U', $last_modified)->format('d-M-Y');
    }

    /**
     * Replace configuration variables in the input string with values for the configuration.
     *
     * @param  string  &$html
     */
    private function replaceVars(&$html)
    {
        preg_match_all($this->getPatternToMatch(), $html, $matches);

        array_walk($matches[1], function (&$match) {
            throw_unless(in_array($match, $this->allowed_configurations), MarkdownExportOfUnApprovedConfiguration::class, $match);

            $match = config($match);
        });

        $html = str_replace($matches[0], $matches[1], $html);
    }

    /**
     * Get the pattern to match
     * We are looking for {CONFIG:something}
     *
     * @return string
     */
    private function getPatternToMatch()
    {
        return "/{CONFIG:([\w.]+)}/";
    }

    /**
     * All documents are rendered similarly. This base render function does the job for all based on
     * parameters for that specific markdown file.
     *
     * @param  string  $file this must be a path resovable by `resource_path()`
     * @param  string  $title the HTML title for the page
     * @return \Inertia\Response;
     */
    private function baseMarkdownRender($file, $title)
    {
        $html = Str::markdown(file_get_contents(resource_path($file)));

        $this->replaceVars($html);

        return Inertia::render('Doc/Page', [
            'markdown' => $html,
            'updated' => $this->getFileUpdatedDate($file),
            'title' => $title,
        ]);
    }

    /**
     * Show the license document
     *
     * @return \Inertia\Response;
     */
    public function license()
    {
        return $this->baseMarkdownRender('markdown/license.md', 'MIT License');
    }

    /**
     * Show the terms and conditions document
     *
     * @return \Inertia\Response;
     */
    public function terms()
    {
        return $this->baseMarkdownRender('markdown/terms.md', 'Terms of Service');
    }

    /**
     * Show the privacy policy document
     *
     * @return \Inertia\Response;
     */
    public function privacy()
    {
        return $this->baseMarkdownRender('markdown/policy.md', 'Privacy Policy');
    }
}
