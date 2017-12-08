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

use cfg;
use Closure;
use dot;
use err;
use hiapi\gogetssl\lib\GoGetSSLApi;

/**
 * GoGetSSL certificate tool.
 *
 * @author Andrii Vasyliev <sol@hiqdev.com>
 */
class GoGetSSLTool extends \hiapi\components\AbstractTool
{
    protected $api;
    protected $isConnected = null;

    protected static $debugCsr = '-----BEGIN CERTIFICATE REQUEST-----
MIIC2TCCAcECAQAwgZMxCzAJBgNVBAYTAkNZMRUwEwYDVQQIDAxHZXJtYXNzb2dl
aWExETAPBgNVBAcMCExpbWFzc29sMRMwEQYDVQQKDApiaXN0cm9ob3N0MQswCQYD
VQQLDAJJVDEXMBUGA1UEAwwOYmlzdHJvaG9zdC5jb20xHzAdBgkqhkiG9w0BCQEW
EGR2YnJpZ2FAaW5ib3gubHYwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIB
AQCeZEh8LP1XCLJajK3gmiBb8ZXzyr0eZqku7wS+DDEauMwkGRrzLFTiYhGUqbDE
u3M8+ylFj1Ks1VE3O5/3izx3lLh6Axw1CHRQ4FrVp3FgqGobhu6kiN9qShy2QDbW
sRNr8qDGsn8cVCQZSPdDOdRo+BuQhDepODyk11sQSQlmDrqory5Am4e5SQj5daaU
2WhvGm+M3yteTln7zxxdjfLbOm80SaFELl5dXVh+SeoJ9tONuuP+pCEETWTmss3U
MMbGSdlPvHKMyLZ9/2BgyxV7IrxzN6pnOOznyR4+hlQhw0EfevYirbCI/usWhwFN
CvDUoXKbKzmayCUPtRj9BURpAgMBAAGgADANBgkqhkiG9w0BAQUFAAOCAQEAW7dM
NebhwSRap7OTf22qirTleB1y0PZguxk9DkwiaPjNlR7asg6Wdksmm8Wq03kk4x8Q
wRwlzaanhH5oNUQ4pmIeyHDr5FfyvqADNNq0PsNQB2hYcYnsCLBgU0iCcj4826sK
kMgENwKdAb3TnZdTE/qQrw2DlRYz+19YDWiqMOQQkGT5sc3aTbuZQfbtUYOs3g6W
1I60eILxGZskXkd418s8Yg2J7qhgJtqUwhTFnisuYXH5eh3+IEXUgAVwQm4j8Tu5
eYKxGQBu3EZ5RvX+mCL/EedxU2G0rICAf7HhXYh+RoiMuklXdVZb6w8+ku4CvWge
LOTzFN1dURYXhRAH7Q==
-----END CERTIFICATE REQUEST-----';

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
        $response = $this->request('reIssueOrder', [$row['order_id'], $row]);

        return $response;
    }

    public function certificateGenerateCSR($row)
    {
        return  $this->request('generateCSR', [$row, $row]);
    }

    public function certificateGetDomainEmails($row)
    {
        return $this->request('getDomainEmails', [['domain' => $row['fqdn']]]);
    }

    public function certificateGetWebservers($row)
    {
        return $this->request('getDomainEmails', [['domain' => $row['fqdn']]]);
    }

    /// ORDER
    public function certificateOrder($row = [])
    {
        $data = $this->_prepareOrderData($row);
        if (err::is($data)) {
            return $data;
        }

        return $this->request('addSSLOrder', [$data]);
    }

    protected function _prepareOrderData($row)
    {
        $row = $this->_prepareOrderContacts($row);
        $row['product'] = $this->_certificateGetProduct($row['product']);
        if (err::is($row)) {
            return $row;
        }

        $fields = $this->_prepareOrderFields($row);
        foreach ($fields as $field => $def) {
            if ($def instanceof Closure) {
                $data[$field] = call_user_func($def, $row);
            } else {
                $data[$field] = dot::get($row, $def);
            }
        }

        return $data;
    }

    protected function _prepareOrderFields($data)
    {
        return [
            'product_id'        => 'product.id',
            'period'            => function ($row) {
                return 12*($row['amount'] ?: 1);
            },
            'dcv_method'        => 'dcv_method',
            'approver_email'    => 'approver_email',
            'server_count'      => function ($row) {
                return $row['server_count'] ?: -1;
            },
            'webserver_type'    => function ($row) {
                return $row['webserver_type'] ?: 'nginx';
            },
            'csr'               => function ($row) {
                if (empty($row['csr']) && cfg::get('DEBUG_GOGETSSL')) {
                    return static::$debugCsr;
                }

                return $row['csr'];
            },
            'admin_firstname'   => 'admin.first_name',
            'admin_lastname'    => 'admin.last_name',
            'admin_email'       => 'admin.email',
            'admin_title'       => function ($row) {
                return $this->_prepareContactTitle($row['admin']);
            },
            'admin_phone'       => function ($row) {
                return $this->_prepareContactPhone($row['admin']['phone']);
            },
            'tech_firstname'    => 'tech.first_name',
            'tech_lastname'     => 'tech.last_name',
            'tech_email'        => 'tech.email',
            'tech_title'        => function ($row) {
                return $this->_prepareContactTitle($row['tech']);
            },
            'tech_phone'        => function ($row) {
                return $this->_prepareContactPhone($row['tech']['phone']);
            },
        ];
    }

    protected function _certificateGetProduct($name)
    {
        $products = $this->certificatesGetAllProducts();

        return $products[$name] ?? null;
    }

    protected function _prepareOrderContacts($row)
    {
        $types = ['admin', 'tech', 'org'];
        $ids = [];
        foreach ($types as $type) {
            $key = $type . '_id';
            if (empty($row[$key])) {
                return err::set($row, 'no data given', ['field' => $key]);
            }
            $ids[$type] = $row[$key];
        }
        $contacts = $this->base->contactsSearch(['ids' => array_unique($ids)]);
        if (err::is($contacts)) {
            return err::set($row, err::get($contacts));
        }
        foreach ($ids as $type => $id) {
            $row[$type] = $contacts[$id];
        }

        return $row;
    }

    protected function _prepareContactTitle($contact)
    {
        return empty($contact['title']) ? 'Mr.' : $row['title'];
    }

    protected function _prepareContactPhone($phone)
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
