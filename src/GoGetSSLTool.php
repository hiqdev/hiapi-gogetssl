<?php
/**
 * hiAPI GoGetSSL plugin
 *
 * @link      https://github.com/hiqdev/hiapi-gogetssl
 * @package   hiapi-gogetssl
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\gogetssl;

use err;
use hiapi\gogetssl\vendor\GoGetSSLApi;

/**
 * GoGetSSL certificate tool.
 *
 * @author Andrii Vasyliev <sol@hiqdev.com>
 */
class GoGetSSLTool extends \hiapi\components\AbstractTool
{
    protected $api;
    protected $isConnected = null;

    protected static $supplier = [
        'comodo' => 1,
        'geotrust' => 2,
        'symantec' => 2,
        'thawte' => 2,
    ];

    protected static $orderRequired =
        'product_id,period,csr,server_count,webserver_type,' .
        'admin_firstname,admin_lastname,admin_phone,admin_title,admin_email,' .
        'dcv_method,' .
        'tech_firstname,tech_lastname,tech_phone,tech_title,tech_email,' .
        'signature_hash';

    protected static $orderParametrs =
        'product_id,period,csr,server_count,approver_email,approver_emails,webserver_type,' .
        'dns_names,admin_firstname,admin_lastname,admin_organization,admin_addressline1,' .
        'admin_phone,admin_title,admin_email,admin_city,admin_country,admin_fax,admin_postalcode,' .
        'admin_region,dcv_method,tech_firstname,tech_lastname,tech_organization,tech_addressline1,' .
        'tech_phone,tech_title,tech_email,tech_city,tech_country,tech_fax,tech_postalcode,' .
        'tech_region,org_name,org_division,org_duns,org_addressline1,org_city,org_country,' .
        'org_fax,org_phone,org_postalcode,org_region,signature_hash';

    protected static $renamedOrderFields = [
        'street1' => 'addressline1',
        'postal_code' => 'postalcode',
        'state' => 'region',
        'first_name' => 'firstname',
        'last_name' => 'lastname',
        'voice_phone' => 'phone',
        'fax_phone' => 'fax',
    ];

    protected static $defaultOrderFieldValue = [
        'title' => 'Mr',
        'server_count' => -1,
        'webserver' => 'nginx',
        'amount' => 1,
        'dcv_method' => 'dns',
        'signature_hash' => 'SHA2',
    ];

    public function __construct($base, $data=null)
    {
        parent::__construct($base, $data);
        $this->api = new GoGetSSLApi(null, $data['url']);
    }

    /// CHECK ERROR
    private function isError($res, $loginRequired = false)
    {
        if (err::is($res) || isset($res['error'])) {
            return true;
        }
        if (!$loginRequired) {
            return false;
        }
        if (empty($res['success'])) {
            return true;
        }

        return !($res['success'] === true);
    }

    protected static function _transformKey($key)
    {
        $key = preg_replace('/[^a-zA-Z0-9_]/','_', $key);
        while (preg_match('/__/', $key)) {
            $key = preg_replace('/__/', '_', $key);
        }

        return trim(strtolower($key), " \t\n\r\0\x0B_");
    }

    /// LOGIN, REQUEST, RESPONSE
    private function login()
    {
        $res = $this->api->auth($this->data['login'], $this->data['password']);

        return $this->response([], $res);
    }

    private function request($command, $args = [], $loginRequired = false)
    {
        if ($this->isConnected === null) {
            $this->isConnected = $this->login();
        }
        if ($this->isError($this->isConnected, $loginRequired)) {
            return $this->isConnected;
        }

        $res = call_user_func_array([$this->api, $command], $args);

        return $this->response(['command' => $command, 'args' => $args], $res, $loginRequired);
    }

    private function response($data = [], $res = null, $loginRequired = false)
    {
        if (err::is($res)) {
            return $res;
        }
        if ($this->isError($res, $loginRequired)) {
            return err::set($data, $res['description'] ?: 'unknown error');
        }
        if (empty($res)) {
            return err::set($data, 'empty response');
        }
        /// ??? return arr::merge($data, $res);

        return $res;
    }

    /// GENERAL COMMANDS
    public function certificatesGetAllProducts()
    {
        $response = $this->request('getAllProducts');
        if (err::is($response)) {
            return $response;
        }

        $res = [];
        foreach ($response['products'] as $product) {
            $product['remoteid'] = $product['id'];
            $eid = $this->_transformKey($product['name']);
            $product['eid'] = $eid;
            $res[$eid] = $product;
        }

        return $res;
    }

    public function certificatesGetAllProductPrices()
    {
        $response = $this->request('getAllProductPrices');
        if (err::is($response)) {
            return $response;
        }

        return $response['product_prices'];
    }

    public function certificateInfo($row)
    {
        return $this->request('getOrderStatus', [$row['remoteid']]);
    }

    public function certificateReissue($row)
    {
        // TODO: Certificate Reissue
    }
}
