<?php
require "vendor/autoload.php";
require_once 'includes/config.php';

use \Slim\App;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Firebase\JWT\JWT;
use Tuupola\Base62;

/*********************************************
Setup the app parameters
**********************************************/
$app = new \Slim\App(["settings" => $config]);
$container = $app->getContainer();

$container['db'] = function ($c) {
  $db_info = $c['settings']['db_info'];

  $db = mysqli_init();
  $db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
  $db->real_connect($db_info['host'], $db_info['user'], $db_info['pass'], $db_info['dbname']);

  return $db;
};


$app->add(new \Slim\Middleware\JwtAuthentication([
    "path" => "/",
    "passthrough" => ["/login"],
    "secret" => $config['jwt_secret'],
    "error" => function (ServerRequestInterface $request, ResponseInterface $response, $arguments) {
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        return $response->withJson($data, 401);
    }
]));


/*********************************************
Begin API endpoints
**********************************************/

$app->get('/', function ($request, $response, $args) {
  $response->write("THIS IS THE DEFAULT PATH");
  return $response;
});

// Login action
$app->post('/login', function (ServerRequestInterface $request, ResponseInterface $response) {

  $response_obj = [];
  $response_code = 500;

  $data = $request->getParsedBody();
  $clean_data = [];
  $clean_data['username'] = filter_var($data['username'], FILTER_SANITIZE_STRING);
  $clean_data['password'] = filter_var($data['password'], FILTER_SANITIZE_STRING);

  $db_data = [];
  if ($result = $this->db->query("SELECT * FROM users WHERE username = '". $clean_data['username']."'")) {
      while($row = $result->fetch_array(MYSQL_ASSOC)) {
              $db_data = $row;
      }
    $result->close();

    if (password_verify($clean_data['password'], $db_data['password_hash'])) {
      $now = new DateTime();
      $future = new DateTime("now +1 month");

      $jti = Base62::encode(random_bytes(16));
      $payload = [
          "iat" => $now->getTimeStamp(),
          "exp" => $future->getTimeStamp(),
          "jti" => $jti,
          "sub" => $clean_data['username']
      ];
      $secret = $this->settings['jwt_secret'];
      $token = JWT::encode($payload, $secret, "HS256");

      $response_obj['success'] = 1;
      $response_obj['token'] = $token;
      $response_code = 200;
    } else {
      $response_obj['success'] = 0;
      $response_obj['message'] = 'Username or password invalid.';
      $response_code = 401;
    }

  } else {
    $response_obj['success'] = 0;
    $response_obj['message'] = 'The DB shit the bed.';
    $response_code = 401;
  }

  return $response->withJson($response_obj, $response_code);
});

// GET all maps
$app->get('/maps', function (ServerRequestInterface $request, ResponseInterface $response) {

  $mapsArray = array();
  if ($result = $this->db->query("SELECT * FROM cloth_maps")) {
      while($row = $result->fetch_array(MYSQL_ASSOC)) {
              $mapsArray[] = $row;
      }
    $result->close();
  }

  $newResponse = $response->withJson($mapsArray);

  return $newResponse;

});

// GET one map
$app->get('/map/{map_id}', function (ServerRequestInterface $request, ResponseInterface $response, $args) {

  $mapObj = array();
  if ($result = $this->db->query("SELECT * FROM cloth_maps WHERE id = " . $args['map_id'])) {
      while($row = $result->fetch_array(MYSQL_ASSOC)) {
              $mapObj = $row;
      }
    $result->close();
  }

  $newResponse = $response->withJson($mapObj);

  return $newResponse;

});

$app->run();

?>
