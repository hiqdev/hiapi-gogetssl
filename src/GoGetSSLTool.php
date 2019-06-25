<?php
/**
 * hiAPI GoGetSSL plugin
 *
 * @link      https://github.com/hiqdev/hiapi-gogetssl
 * @package   hiapi-gogetssl
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017-2018, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\gogetssl;

use Closure;
use hiapi\legacy\lib\deps\err;
use hiapi\legacy\lib\deps\dot;
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
        $info = $this->request('getOrderStatus', [$row['remoteid']]);
        if (err::is($info)) {
            return $info;
        }
        $info['name'] = $info['domain'];
        $info['begins'] = $info['valid_from'] === '0000-00-00' ? '' : $info['valid_from'];
        $info['expires'] = $info['valid_till'] === '0000-00-00' ? '' : $info['valid_till'];

        $info['dcv_data'] = [];
        if ($info['dcv_method'] === 'email') {
            $info['dcv_data']['email'] = $info['approver_method'];
        } elseif ($info['dcv_method'] === 'dns') {
            $info['dcv_data']['dns'] = $info['approver_method']['dns'];
        }

        $info['dcv_data_alternate'] = $this->request('getDomainAlternative', [$info['csr_code']]);

        return $info;
    }

    public function certificateGenerateCSR($row)
    {
        return $this->request('generateCSR', [$row]);
    }

    public function certificateDecodeCSR($row)
    {
        $res = $this->request('decodeCSR', [$row['csr'], $row['brand'], $row['wildcard']]);
        if (err::is($res)) {
            return $res;
        }
        $info = $res['csrResult'];
        static $fields = [
            'CN'     => 'csr_commonname',
            'O'      => 'csr_organization',
            'OU'     => 'csr_department',
            'L'      => 'csr_city',
            'S'      => 'csr_state',
            'C'      => 'csr_country',
            'Email'  => 'csr_email',
        ];
        $data = [
            'csr' => $row['csr'],
        ];
        foreach ($fields as $from => $field) {
            $data[$field] = empty($info[$from]) ? null : $info[$from];
        }

        return $data;
    }

    public function certificateGetDomainEmails($row)
    {
        $res = $this->request('getDomainEmails', [$row['domain']]);
        $emails = [];
        foreach ($res as $list) {
            if (is_array($list)) {
                $emails = array_merge($emails, $list);
            }
        }

        return array_unique($emails);
    }

    public function certificateGetWebserverTypes($row)
    {
        $supplier_id = 'comodo' === strtolower($row['supplier']) ? 1 : 2;

        $data = $this->request('getWebservers', [$supplier_id]);
        if (err::is($data)) {
            return $data;
        }

        return $data['webservers'];
    }

    public function certificateSendNotifications($row)
    {
        if ($row['dcv_method'] !== 'email') {
            return err::set($row, 'email is required dcv method');
        }

        return $this->request('resendValidationEmail', [$row['remoteid'], ['domain' => $row['fqdn']]]);
    }

    public function certificateRevalidate($row)
    {
        if ($row['dcv_method'] === 'email') {
            return err::set($row, 'dcv method is not compatible with operation');
        }

        return $this->request('revalidateCN', [$row['remoteid'], ['domain' => $row['fqdn']]]);
    }

    public function certificateChangeValidation($row)
    {
        return $this->request('changeValidationMethod', [
            $row['remoteid'], [
            'domain' => $row['fqdn'],
            'new_method' => $row['dcv_method'] === 'email' ? $row['approver_email'] : $row['dcv_method'],
        ]]);
    }
    /// ISSUE, REISSUE, RENEW
    public function certificateIssue($row = [])
    {
        $data = $this->_prepareOrderData($row);
        if (err::is($data)) {
            return $data;
        }

        return $this->request('addSSLOrder', [$data]);
    }

    public function certificateRenew($row = [])
    {
        $data = $this->_prepareOrderData($row);
        if (err::is($data)) {
            return $data;
        }

        return $this->request('addSSLRenewOrder', [$data]);
    }

    public function certificateReissue($row)
    {
        $response = $this->request('reIssueOrder', [$row['order_id'], $row]);

        return $response;
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
                return ((int) $row['webserver_type']) ?: 1;
            },
            'csr'               => 'csr',
            'admin_firstname'   => 'admin.first_name',
            'admin_lastname'    => 'admin.last_name',
            'admin_email'       => 'admin.email',
            'admin_organization'=> 'admin.organization',
            'admin_city'        => 'admin.city',
            'admin_country'     => 'admin.country',
            'admin_title'       => function ($row) {
                return $this->_prepareContactTitle($row['admin']);
            },
            'admin_phone'       => function ($row) {
                return $this->_prepareContactPhone($row['admin']['phone']);
            },
            'admin_fax'         => function ($row) {
                if (empty($row['admin']['fax'])) {
                    return $this->_prepareContactPhone($row['admin']['phone']);
                }

                return $this->_prepareContactPhone($row['admin']['fax']);
            },
            'tech_firstname'    => 'tech.first_name',
            'tech_lastname'     => 'tech.last_name',
            'tech_email'        => 'tech.email',
            'tech_organization' => 'tech.organization',
            'tech_city'         => 'tech.city',
            'tech_country'      => 'tech.country',
            'tech_title'        => function ($row) {
                return $this->_prepareContactTitle($row['tech']);
            },
            'tech_phone'        => function ($row) {
                return $this->_prepareContactPhone($row['tech']['phone']);
            },
            'org_name'          => 'org.organization',
            'org_division'      => 'org.organization',
            'org_addressline1'  => 'org.street1',
            'org_city'          => 'org.city',
            'org_country'       => 'org.country',
            'org_phone'         => 'org.phone',
            'org_postalcode'    => 'org.postal_code',
            'org_region'        => 'org.province',
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

    /// CANCEL
    public function certificateCancel($row)
    {
        return $this->request('cancelSSLOrder', [$row['remoteid'], $row['reason']]);
    }

}
