<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2018  Carlos Garcia Gomez  <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Core\App;

/**
 * Description of AppRouter
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class AppRouter
{

    /**
     * Path to list of routes stored on file.
     */
    const ROUTE_LIST_FILE = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR . 'routes.json';

    /**
     * List of routes.
     *
     * @var array
     */
    private $routes;

    /**
     * AppRouter constructor.
     */
    public function __construct()
    {
        if (!defined('FS_ROUTE')) {
            define('FS_ROUTE', '');
        }

        $this->routes = $this->loadFromFile();
    }

    /**
     * Clear the App routes.
     */
    public function clear()
    {
        $this->routes = [];
        $this->save();
    }

    /**
     * Return the especific App controller for any kind of petition.
     *
     * @return App
     */
    public function getApp()
    {
        $uri = $this->getUri();
        if ('/api' === $uri || '/api/' === substr($uri, 0, 5)) {
            return new AppAPI($uri);
        }

        if ('/cron' === $uri) {
            return new AppCron($uri);
        }

        foreach ($this->routes as $key => $data) {
            if ($uri === $key) {
                return new AppController($uri, $data['controller']);
            }

            if ('*' !== substr($key, -1)) {
                continue;
            }

            if (0 === strncmp($uri, $key, strlen($key) - 1)) {
                return new AppController($uri, $data['controller']);
            }
        }

        return new AppController($uri);
    }

    /**
     * Return true if can output a file, false otherwise.
     *
     * @return bool
     */
    public function getFile(): bool
    {
        $uri = $this->getUri();
        $filePath = FS_FOLDER . $uri;

        /// Not a file?
        if (!is_file($filePath)) {
            return false;
        }

        /// Allowed folder?
        $allowedFolders = ['node_modules', 'vendor', 'Dinamic', 'Core', 'Plugins'];
        foreach ($allowedFolders as $folder) {
            if ('/' . $folder === substr($uri, 0, strlen($folder) + 1)) {
                header('Content-Type: ' . $this->getMime($filePath));
                readfile($filePath);
                return true;
            }
        }

        return false;
    }

    /**
     * Adds this route to the ap routes.
     *
     * @param string $newRoute
     * @param string $controllerName
     * @param string $optionalId
     */
    public function setRoute(string $newRoute, string $controllerName, string $optionalId = '')
    {
        if (!empty($optionalId)) {
            /// if optionaId, then remove previous items with that data
            foreach ($this->routes as $route => $routeItem) {
                if ($routeItem['controller'] === $controllerName && $routeItem['optionalId'] === $optionalId) {
                    unset($this->routes[$route]);
                }
            }
        }

        $this->routes[$newRoute] = [
            'controller' => $controllerName,
            'optionalId' => $optionalId
        ];

        $this->save();
    }

    /**
     * Return the mime type from given file.
     *
     * @param string $filePath
     *
     * @return string
     */
    private function getMime(string $filePath)
    {
        if (substr($filePath, -4) === '.css') {
            return 'text/css';
        }

        if (substr($filePath, -3) === '.js') {
            return 'application/javascript';
        }

        return mime_content_type($filePath);
    }

    /**
     * Return the uri from the request.
     *
     * @return bool|string
     */
    private function getUri()
    {
        $uri = filter_input(INPUT_SERVER, 'REQUEST_URI');
        $uri2 = ($uri === null) ? filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL) : $uri;
        $uriArray = explode('?', $uri2);

        return substr($uriArray[0], strlen(FS_ROUTE));
    }

    /**
     * Returns an array with the list of plugins in the plugin.list file.
     *
     * @return array
     */
    private function loadFromFile(): array
    {
        if (file_exists(self::ROUTE_LIST_FILE)) {
            $content = file_get_contents(self::ROUTE_LIST_FILE);
            if ($content !== false) {
                return json_decode($content, true);
            }
        }

        return [];
    }

    /**
     * Save the routes in a file.
     *
     * @return bool
     */
    private function save(): bool
    {
        $content = json_encode($this->routes);
        return file_put_contents(self::ROUTE_LIST_FILE, $content) !== false;
    }
}
