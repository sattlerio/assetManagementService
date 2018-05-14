<?php
/**
 * Created by PhpStorm.
 * User: ghovat
 * Date: 14.05.18
 * Time: 13:23
 */

class GuardianHandler
{
    private $guardian_url;
    private $user_uuid;
    private $company_id;

    /**
     * @param mixed $company_id
     */
    public function setCompanyId($company_id)
    {
        $this->company_id = $company_id;
    }
    /**
    * @param mixed $guardian_url
    */
    public function setGuardianUrl($guardian_url)
    {
        $this->guardian_url = $guardian_url . $this->user_uuid . '/' . $this->company_id;
    }

    /**
     * @param mixed $user_uuid
     */
    public function setUserUuid($user_uuid)
    {
        $this->user_uuid = $user_uuid;
    }

    public function getPermission() {
        try {
            $client = new GuzzleHttp\Client();
            $res = $client->request("GET", $this->guardian_url);

            if ($res->getStatusCode() != 200) {
                return false;
            }
            return $res;

        } catch (GuzzleHttp\Exception\GuzzleException $exception){
            return $exception;
        }
         catch (Exception $exception) {
            return $exception;
        }
    }

}