<?php

namespace AMWhalen\ArchiveMyTweets;

class View {

    protected $templateDirectory;

    public function __construct($dir) {
        $this->setTemplateDirectory($dir);
    }

    /**
     * Sets the template directory
     *
     * @return bool Returns TRUE on success, FALSE if the directory is invalid.
     */
    public function setTemplateDirectory($dir) {
        if (!is_dir($dir)) {
            throw new \Exception('Template directory is invalid: ' . $dir);
        }
        $this->templateDirectory = rtrim($dir, '/');
        return true;
    }

    /**
     * Render a template with optional data
     *
     */
    public function render($template, $data=array(), $returnRenderedTemplate=false) {

        $templatePath = $this->templateDirectory . '/' . ltrim($template, '/');
        if (!file_exists($templatePath)) {
            throw new \Exception('Template not found: ' . $templatePath);
        }

        if ($returnRenderedTemplate) {
            return $this->_render($templatePath, $data);
        } else {
            echo $this->_render($templatePath, $data);
        }

    }

    /**
     * Returns a rendered template string
     */
    private function _render($templatePath, $data) {

        // extract data variables into the local scope
        extract($data);
        ob_start();
        require $templatePath;
        return ob_get_clean();

    }

}