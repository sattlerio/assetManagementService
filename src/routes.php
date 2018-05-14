<?php

use Slim\Http\Request;
use Slim\Http\Response;

$FILE_TYPES = [
    "image/png",
    "image/jpg",
    "image/jpeg"
];

// Routes

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});


$app->post("/upload/{company_id}", function (Request $request, Response $response, array $args) {

    if ($request->hasHeader("X-TransactionID")) {
        $transactionId = $request->getHeader("X-TransactionId")[0];
    } else {
        $transactionId = uniqid();
    }
    $companyId = $args["company_id"];

    $this->logger->info("$transactionId: new request for uploading a asset to S3");

    if (!$request->hasHeader("X-User-UUID")) {
        $this->logger->info("$transactionId: missing user uuid in header abort transaction");
        $data = array("status" => "ERROR", "status_code" => 400, "message" => "missing authorization header", "transaction_id" => $transactionId);
        $newResponse = $response->withJson($data)->withStatus(400);
        return $newResponse;
    }
    $userUuid = $request->getHeader("X-User-UUID")[0];

    $uploadedFiles = $request->getUploadedFiles();
    if(!$uploadedFiles || !array_key_exists("file", $uploadedFiles)) {
        $this->logger->info("$transactionId: abort transaction because of missing files");
        $data = array("status" => "ERROR", "status_code" => 400, "message" => "you have to pload at least one file", "transaction_id" => $transactionId);
        $newResponse = $response->withJson($data)->withStatus(400);
        return $newResponse;
    }

    $this->logger->info("$transactionId: request seems valid going to check guardian permission");

    $guardianClient = new GuardianHandler();
    $guardianClient->setCompanyId($companyId);
    $guardianClient->setUserUuid($userUuid);
    $guardianClient->setGuardianUrl(getenv("GUARDIAN_URL"));

    $guardian_response = $guardianClient->getPermission();

    if($guardian_response instanceof Exception) {
        $this->logger->info("$transactionId: guardian handler raised a exception");
        $this->logger->warn($guardian_response);
        $data = array("status" => "ERROR",
            "status_code" => 500,
            "message" => "Exception while checking permission",
            "transaction_id" => $transactionId);
        $newResponse = $response->withJson($data)->withStatus(500);
        return $newResponse;
    }
    if(!$guardian_response) {
        $this->logger->info("$transactionId: error during guardian communication, abort...");
        $data = array("status" => "ERROR",
            "status_code" => 500,
            "message" => "Exception while checking permission",
            "transaction_id" => $transactionId);
        $newResponse = $response->withJson($data)->withStatus(500);
        return $newResponse;
    }

    $guardianData = json_decode($guardian_response->getBody());

    $this->logger->info("$transactionId: successful communication with Guardian");

    if (!array_key_exists("status", $guardianData) || !array_key_exists("data", $guardianData)) {
        $this->logger->info("$transactionId: invalid response from guardian");
        $data = array("status" => "ERROR",
            "status_code" => 500,
            "message" => "Exception while checking permission",
            "transaction_id" => $transactionId);
        $newResponse = $response->withJson($data)->withStatus(500);
        return $newResponse;
    }

    if (!array_key_exists("user_permission", $guardianData->data) || $guardianData->status != "OK") {
        $this->logger->info("$transactionId: invalid response from guardian");
        $data = array("status" => "ERROR",
            "status_code" => 500,
            "message" => "Exception while checking permission",
            "transaction_id" => $transactionId);
        $newResponse = $response->withJson($data)->withStatus(500);
        return $newResponse;
    }

    $userPermission = $guardianData->data->user_permission;

    $this->logger->info("$transactionId: got user permission from guardian -- $userPermission --");

    if (0 > $userPermission || $userPermission > 3) {
        $this->logger->info("$transactionId: user has not the required permission - abort...");
        $data = array("status" => "ERROR",
            "status_code" => 401,
            "message" => "Wrong user permissions",
            "transaction_id" => $transactionId);
        $newResponse = $response->withJson($data)->withStatus(401);
        return $newResponse;
    }

    $this->logger->info("$transactionId: successfully validated user permission going to upload file");

    try {
        $sharedConfig = [
            "region" => "eu-west-1",
            "version" => "latest"
        ];

        $sdk = new Aws\Sdk($sharedConfig);
        $s3Client = $sdk->createS3();
        $bucketList = $s3Client->listBuckets();

        $bucketName = "sattlerio-" . strtolower($companyId);
        $val = validateBucketList($bucketName, $bucketList);

        if ($val === true) {
            $this->logger->info("$transactionId: bucket exists");
        } elseif ($val === false) {
            $this->logger->info("$transactionId: bucket does not exist, going to create it");
            $result = $s3Client->createBucket([
                'Bucket' => $bucketName,
            ]);
        } else {
            $this->logger->warn($val);
            $this->logger->info("$transactionId: problem validating the buckets");
            $data = array("status" => "ERROR",
                "status_code" => 500,
                "message" => "internal server error",
                "transaction_id" => $transactionId);
            $newResponse = $response->withJson($data)->withStatus(500);
            return $newResponse;
        }
        $uploadedFile = $uploadedFiles["file"];
        global $FILE_TYPES;
        if ($uploadedFile->getError() === UPLOAD_ERR_OK && in_array($uploadedFile->getClientMediaType(), $FILE_TYPES) && get_extension($uploadedFile->getClientFilename())) {
            $file_name = uuid() . '.' . get_extension($uploadedFile->getClientFilename());

            $result = $s3Client->upload($bucketName, $file_name, $uploadedFile->getStream());

            $this->logger->info("$transactionId: successfully uploaded file >> $file_name");
            $this->logger->info($result);
            $data = array("status" => "OK",
                "status_code" => 200,
                "message" => "succesfully uploaded file",
                "file_name" => $file_name,
                "object_url" => $result['ObjectURL'],
                "transaction_id" => $transactionId);
            $newResponse = $response->withJson($data)->withStatus(200);
            return $newResponse;

        }
        $this->logger->info("$transactionId: not possible to validate uploaded file");
        $data = array("status" => "ERROR",
            "status_code" => 400,
            "message" => "problem processing the uploaded file",
            "transaction_id" => $transactionId);
        $newResponse = $response->withJson($data)->withStatus(500);
        return $newResponse;

    } catch (Exception $exception) {
        $this->logger->info("$transactionId: error during communication with AWS S3");
        $this->logger->warn($exception->getTraceAsString());
        $this->logger->warn($exception->getMessage());
        $data = array("status" => "ERROR",
            "status_code" => 500,
            "message" => "internal server error",
            "transaction_id" => $transactionId);
        $newResponse = $response->withJson($data)->withStatus(500);
        return $newResponse;
    }
});
