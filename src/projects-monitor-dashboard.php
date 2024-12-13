<?php

class ProjectsMonitorDashboard
{
    private $repositories;

    public function __construct($repositories)
    {
        $this->repositories = $repositories;
    }

    public function display()
    {
        foreach ($this->repositories as $repo) {
            echo "<div>Repository: {$repo['name']} - Size Label File: ";
            echo $repo['size_label_file_exists'] ? 'Exists' : 'Missing';
            echo "</div>";
        }
    }

}
