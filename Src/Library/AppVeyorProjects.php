<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

class AppVeyorProjects
{
    private $request;

    public function __construct()
    {
        $this->request = new Request();
    }

    public function getProjects()
    {
        $url = 'https://ci.appveyor.com/api/projects';
        $response = $this->request->get($url, [
            'headers' => [
                'Authorization' => 'Bearer YOUR_API_TOKEN',
                'Content-Type' => 'application/json'
            ]
        ]);

        return json_decode($response->getBody(), true);
    }
}

?>
