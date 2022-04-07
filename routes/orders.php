<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use GuzzleHttp\Client;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';


$app = AppFactory::create();

//Fetch all orders
$app->get('/api/offline/orders/all', function (Request $request, Response $response) {
    $sql = "Select * from orders";
    try {
        $db = new DB();
        $conn = $db->connect();
        $stmt = $conn->query($sql);
        $orders = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        $response->getBody()->write(json_encode($orders));
        return $response->withHeader('content-type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $$response->getBody()->write(json_encode($error));
        return $response->withHeader('content-type', 'application/json')->withStatus(500);
    }
});

//Fetch orders that havent been synchronized
$app->get('/api/offline/orders/nosync', function (Request $request, Response $response) {
    $sql = "Select * from orders where sync = 0";
    try {
        $db = new DB();
        $conn = $db->connect();
        $stmt = $conn->query($sql);
        $orders = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        $response->getBody()->write(json_encode($orders));
        return $response->withHeader('content-type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $$response->getBody()->write(json_encode($error));
        return $response->withHeader('content-type', 'application/json')->withStatus(500);
    }
});
//Fetch orders that have been synchronized
$app->get('/api/offline/orders/synched', function (Request $request, Response $response) {
    $sql = "Select * from orders where sync = 1";
    try {
        $db = new DB();
        $conn = $db->connect();
        $stmt = $conn->query($sql);
        $orders = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        $response->getBody()->write(json_encode($orders));
        return $response->withHeader('content-type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $$response->getBody()->write(json_encode($error));
        return $response->withHeader('content-type', 'application/json')->withStatus(500);
    }
});


//Fetch specific order

$app->get('/api/offline/orders/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $sql = "Select * from orders where id = $id";
    try {
        $db = new DB();
        $conn = $db->connect();
        $stmt = $conn->query($sql);
        $orders = $stmt->fetch(PDO::FETCH_OBJ);
        $db = null;
        $response->getBody()->write(json_encode($orders));
        return $response->withHeader('content-type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $$response->getBody()->write(json_encode($error));
        return $response->withHeader('content-type', 'application/json')->withStatus(500);
    }
});
//make an order
$app->post('/api/offline/orders/add', function (Request $request, Response $response, array $args) {
    $flavor = $request->getParam('flavor');
    $quantity = $request->getParam('quantity');
    $orderto = $request->getParam('orderto');
    $sql = "Insert Into orders (flavor,quantity,orderto,orderdate, sync) values (:flavor, :quantity, :orderto,'" . date('Y-m-d H:i:s') . "',0)";
    try {
        $db = new DB();
        $conn = $db->connect();
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':flavor', $flavor);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':orderto', $orderto);
        $result = $stmt->execute();
        $db = null;
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('content-type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('content-type', 'application/json')->withStatus(500);
    }
});
//edit an order
$app->put('/api/offline/orders/edit/{id}', function (Request $request, Response $response, array $args) {
    $flavor = $request->getParam('flavor');
    $quantity = $request->getParam('quantity');
    $orderto = $request->getParam('orderto');
    $id = $args['id'];
    $sql = "Update orders set flavor=:flavor, quantity=:quantity, orderto=:orderto, sync = 0 where id = $id";
    try {
        $db = new DB();
        $conn = $db->connect();
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':flavor', $flavor);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':orderto', $orderto);

        $result = $stmt->execute();
        $db = null;
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('content-type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $$response->getBody()->write(json_encode($error));
        return $response->withHeader('content-type', 'application/json')->withStatus(500);
    }
});
//delete an order
$app->delete('/api/offline/orders/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $sql = "delete from orders where id = $id";
    try {
        $db = new DB();
        $conn = $db->connect();
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute();
        $db = null;
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('content-type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('content-type', 'application/json')->withStatus(500);
    }
});

// Online Software
// synchronize databases
$app->get('/api/sync', function (Request $request, Response $response) {
    try {
        $errors = 0;
        $i = 0;
        $u = 0;
        $db = new DB();
        $conn = $db->connect();
        $conn2 = $db->connect2();
        //rows to update and insert
        $sql = "SELECT c.*, CASE
        WHEN c.id NOT IN ( select id from `online-db`.orders ) THEN 1
        WHEN c.id IN ( select id from `online-db`.orders ) THEN 2
        END AS Test
        FROM  `not-online`.orders c where sync = 0;";
        $stmt = $conn->query($sql);
        $orders = $stmt->fetchAll();
        $count = $stmt->rowCount();
        //rows to delete
        $del = $conn->prepare('delete from `online-db`.orders where id not in (select id from `not-online`.orders);');
        $del->execute();
        $d = $del->rowCount();
        if ( $count > 0 || $d >0) {
            foreach ($orders as $row) {
                if ($row['Test'] == '1') {
                    $insert1 = "Insert into orders values ('" . $row['id'] . "', '" . $row['flavor'] . "', '" . $row['quantity'] . "', '" . $row['orderto'] . "', '" . $row['orderdate'] . "', '1')";
                    $update1 = "Update orders set sync = 1 where id =  '" . $row['id'] . "'";
                    if ($conn2->query($insert1) && $conn->query($update1)) {
                        // echo 'Order to'. $row['orderto'] .'has been synched           |       ';
                        $i++;
                    } else {
                        $errors++;
                    }
                } else if ($row['Test'] == '2') {
                    $update2 = "Update orders set flavor = '" . $row['flavor'] . "', quantity = '" . $row['quantity'] . "', orderto = '" . $row['orderto'] . "', orderdate = '" . $row['orderdate'] . "', sync=  '1' where id = '" . $row['id'] . "';";
                    $update3 = "Update orders set sync = 1 where id =  '" . $row['id'] . "'";
                    if ($conn2->query($update2) && $conn->query($update3)) {
                        // echo 'Order to '. $row['orderto'] .' has been Updated     |     ';
                        $u++;
                    } else {
                        $errors++;
                    }
                }
            }
            //$conn2->query('delete from `online-db`.orders where id not in (select id from `not-online`.orders);');

            $result = 'Synchronization Successfull with ' . $errors . ' errors! ' . $i . ' row(s) inserted, ' . $u . ' row(s) updated and ' . $d . ' row(s) deleted. An email will be sent to the admin.';
            //echo $errors.' errors';
            sendVerificationEmail('abuelwiss8@gmail.com', $errors, $i, $u, $d);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('content-type', 'application/json')->withStatus(200);
        } else {
            $result = "Nothing To synchronize";
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('content-type', 'application/json')->withStatus(200);
        }
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('content-type', 'application/json')->withStatus(500);
    }
});

$app->get('/api/sync2', function (Request $request, Response $response) {
    $client = new Client([
        // Base URI is used with relative requests
        'base_uri' => 'http://localhost:8000',
        'defaults' => [
            'exceptions' => false
        ]
    ]);

    $response = $client->request('GET', '/api/synchronize');
    return $response;
});
//fetch all online orders
$app->get('/api/online/orders/all', function (Request $request, Response $response) {
    $sql = "Select * from orders";
    try {
        $db = new DB();
        $conn = $db->connect2();
        $stmt = $conn->query($sql);
        $orders = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        $response->getBody()->write(json_encode($orders));
        return $response->withHeader('content-type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $$response->getBody()->write(json_encode($error));
        return $response->withHeader('content-type', 'application/json')->withStatus(500);
    }
});

$app->get('/api/online/orders/all2', function (Request $request, Response $response) {
    $client = new Client([
        // Base URI is used with relative requests
        'base_uri' => 'http://localhost:8000',
        'defaults' => [
            'exceptions' => false
        ]
    ]);

    $response = $client->request('GET', '/api/online/orders/all');
    return $response;
});


function sendVerificationEmail($email, $errors, $i, $u, $d)
{
    $mail = new PHPMailer;
    //$mail->SMTPDebug=3;
    $mail->isSMTP();

    $mail->Host = "smtp.gmail.com";
    $mail->Port = 587;
    $mail->SMTPSecure = "tls";
    $mail->SMTPAuth = true;
    $mail->Username = "wassimorders@gmail.com";
    $mail->Password = "P@ssw0rd961";
    $mail->addAddress($email, "User Name");
    $mail->Subject = "Synchronization Successfull with " . $errors . " errors !";
    $mail->isHTML();
    $mail->Body = "You have successfully synchronized data from the offline database to the online one.<br>Number of rows inserted: " . $i . "<br>Number of rows updated: " . $u . "<br>Number of rows deleted: " . $d . "";
    $mail->From = "flagm@gmail.com";
    $mail->FromName = "Flag M";

    if ($mail->send()) {
        // echo "Email Has Been Sent Your Email Address";
    } else {
        echo "Failed To Sent An Email To Your Email Address";
    }
}
