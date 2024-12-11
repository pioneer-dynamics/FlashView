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
        'support.security',
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
        preg_match_all($this->getConfigPatternToMatch(), $html, $matches);

        array_walk($matches[1], function (&$match) {
            throw_unless(in_array($match, $this->allowed_configurations), MarkdownExportOfUnApprovedConfiguration::class, $match);

            $match = config($match);
        });

        $html = str_replace($matches[0], $matches[1], $html);
    }
    
    private function replaceRoutes(&$html)
    {
        preg_match_all($this->getRoutePatternToMatch(), $html, $matches);

        array_walk($matches[1], function (&$match) {
            $match = route($match);
        });

        $html = str_replace($matches[0], $matches[1], $html);
    }

    /**
     * Get the pattern to match
     * We are looking for {CONFIG:something}
     *
     * @return string
     */
    private function getConfigPatternToMatch()
    {
        return "/{CONFIG:([\w.]+)}/";
    }
    
    private function getRoutePatternToMatch()
    {
        return "/{ROUTE:([\w.]+)}/";
    }

    /**
     * All documents are rendered similarly. This base render function does the job for all based on
     * parameters for that specific markdown file.
     *
     * @param  string  $file this must be a path resovable by `resource_path()`
     * @param  string  $title the HTML title for the page
     * @param  bool    $showUpdatedAt = true
     * @return \Inertia\Response;
     */
    private function baseMarkdownRender($file, $title, $showUpdatedAt = true)
    {
        $markdown = file_get_contents(resource_path($file));

        $this->replaceVars($markdown);
        
        $this->replaceRoutes($markdown);

        $html = Str::markdown($markdown);

        return Inertia::render('Doc/Page', [
            'markdown' => $html,
            'updated' => $this->getFileUpdatedDate($file),
            'title' => $title,
            'showUpdatedAt' => $showUpdatedAt,
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
   
    public function faq()
    {
        return $this->baseMarkdownRender('markdown/faq.md', 'F.A.Q.', false);
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
    
    public function security()
    {
        return $this->baseMarkdownRender('markdown/security.md', 'Security');
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
    
    public function about()
    {
        return $this->baseMarkdownRender('markdown/about.md', 'About Us', false);
    }
    
    public function useCases()
    {
        return $this->baseMarkdownRender('markdown/use-cases.md', 'Use Cases', false);
    }
}
