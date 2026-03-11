<?php
/**
 * Front Controller - Mọi request đều đi qua đây
 * Social App - MVC Architecture
 */
require_once 'config/session.php';
require_once 'config/constants.php';
require_once 'config/database.php';

// Load core classes
require_once 'app/core/Database.php';
require_once 'app/core/BaseModel.php';
require_once 'app/core/BaseController.php';
require_once 'app/core/Router.php';

// Khởi động router
$router = new Router();
$router->dispatch();
