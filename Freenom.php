<?php

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class Registrar_Adapter_Freenom extends Registrar_AdapterAbstract
{
    public $config = [
        'email'    => null,
        'password' => null,
    ];

    public function __construct($options)
    {
        if (isset($options['email']) && !empty($options['email'])) {
            $this->config['email'] = $options['email'];
            unset($options['email']);
        } else {
            throw new Registrar_Exception(
                'The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing',
                [':domain_registrar' => 'Freenom', ':missing' => 'API email'],
                3001
            );
        }

        if (isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception(
                'The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing',
                [':domain_registrar' => 'Freenom', ':missing' => 'API password'],
                3001
            );
        }
    }

    public static function getConfig()
    {
        return [
            'label' => 'Manages domains on Freenom via API',
            'form'  => [
                'email' => ['text', [
                    'label'       => 'Freenom API Email',
                    'description' => 'Your Freenom account login email',
                ]],
                'password' => ['password', [
                    'label'         => 'Freenom API Password',
                    'description'   => 'Your Freenom API password',
                    'renderPassword'=> true,
                ]],
            ],
        ];
    }

    private function apiBase()
    {
        return 'https://api.freenom.com/v2';
    }

    /**
     * Generic Freenom API request handler
     */
    private function call($endpoint, $method = 'GET', $params = [])
    {
        $params['email']    = $this->config['email'];
        $params['password'] = $this->config['password'];

        $client = $this->getHttpClient()->withOptions([
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $url = $this->apiBase() . $endpoint;

        $opts = [];
        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
        } else {
            $opts['body'] = $params;
        }

        try {
            $response = $client->request($method, $url, $opts);
            $raw = $response->getContent();
        } catch (HttpExceptionInterface $e) {
            throw new Registrar_Exception("Freenom API connection error: {$e->getMessage()}");
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new Registrar_Exception("Invalid Freenom API format: {$raw}");
        }

        if (isset($data['status']) && strtolower($data['status']) === 'error') {
            $error = $data['error'] ?? 'Unknown Freenom API error';
            throw new Registrar_Exception($error);
        }

        return $data;
    }

    /**
     * -------------------------------
     * DOMAIN AVAILABILITY CHECK
     * -------------------------------
     */
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $data = $this->call(
            '/domain/search',
            'GET',
            [
                'domainname' => $domain->getName(),
                'domaintype' => 'PAID',
            ]
        );

        return isset($data['domain'][0]['status'])
            && $data['domain'][0]['status'] === 'AVAILABLE';
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        // Freenom does not have a direct transfer eligibility API
        // We check pricing availability for transfer:
        $name = $domain->getName();

        try {
            $this->call('/domain/transfer/price', 'GET', [
                'domainname' => $name,
                'authcode'   => 'INVALID',
            ]);
        } catch (Registrar_Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * -------------------------------
     * REGISTER DOMAIN
     * -------------------------------
     */
    public function registerDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        // Freenom requires contact IDs — so create or update a contact
        $contactId = $this->createOrUpdateContact($c);

        $nameservers = array_filter([
            $domain->getNs1(),
            $domain->getNs2(),
            $domain->getNs3(),
            $domain->getNs4(),
        ]);

        if (count($nameservers) < 2) {
            throw new Registrar_Exception("Freenom requires at least 2 nameservers.");
        }

        $params = [
            'domainname' => $domain->getName(),
            'domaintype' => 'PAID',
            'period'     => $domain->getRegistrationPeriod() . 'Y',
            'owner_id'   => $contactId,
        ];

        foreach ($nameservers as $ns) {
            $params['nameserver'][] = $ns;
        }

        $result = $this->call('/domain/register', 'POST', $params);

        $d = $result['domain'][0];
        return $d['status'] === 'REGISTERED';
    }

    /**
     * -------------------------------
     * RENEW DOMAIN
     * -------------------------------
     */
    public function renewDomain(Registrar_Domain $domain)
    {
        $params = [
            'domainname' => $domain->getName(),
            'period'     => $domain->getRenewalPeriod() . 'Y',
        ];

        $data = $this->call('/domain/renew', 'POST', $params);

        return isset($data['domain'][0]['status'])
            && $data['domain'][0]['status'] === 'RENEWED';
    }

    /**
     * -------------------------------
     * MODIFY NAMESERVERS
     * -------------------------------
     */
    public function modifyNs(Registrar_Domain $domain)
    {
        $params = [
            'domainname' => $domain->getName(),
        ];

        foreach ([
            $domain->getNs1(),
            $domain->getNs2(),
            $domain->getNs3(),
            $domain->getNs4(),
        ] as $ns) {
            if ($ns) {
                $params['nameserver'][] = $ns;
            }
        }

        if (!isset($params['nameserver']) || count($params['nameserver']) < 2) {
            throw new Registrar_Exception("Freenom requires minimum 2 nameservers.");
        }

        $data = $this->call('/domain/modify', 'PUT', $params);
        return isset($data['domain'][0]['status']) && $data['domain'][0]['status'] === 'MODIFIED';
    }

    /**
     * -------------------------------
     * MODIFY CONTACT INFORMATION
     * -------------------------------
     */
    public function modifyContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        $contactId = $this->createOrUpdateContact($c);

        $params = [
            'domainname' => $domain->getName(),
            'owner_id'   => $contactId,
            'admin_id'   => $contactId,
            'tech_id'    => $contactId,
            'billing_id' => $contactId,
        ];

        $data = $this->call('/domain/modify', 'PUT', $params);
        return isset($data['domain'][0]['status'])
            && $data['domain'][0]['status'] === 'MODIFIED';
    }

    /**
     * -------------------------------
     * GET DOMAIN DETAILS
     * -------------------------------
     */
    public function getDomainDetails(Registrar_Domain $domain)
    {
        $data = $this->call('/domain/getinfo', 'GET', [
            'domainname' => $domain->getName(),
        ]);

        $d = $data['domain'][0];

        $domain->setExpirationTime(strtotime($d['expirationdate']));
        $domain->setPrivacyEnabled(($d['idshield'] ?? '') === 'enabled');
        $domain->setEpp($d['authcode'] ?? '');

        if (isset($d['nameserver'])) {
            $list = $d['nameserver'];
            if (isset($list[0]['hostname'])) $domain->setNs1($list[0]['hostname']);
            if (isset($list[1]['hostname'])) $domain->setNs2($list[1]['hostname']);
            if (isset($list[2]['hostname'])) $domain->setNs3($list[2]['hostname']);
            if (isset($list[3]['hostname'])) $domain->setNs4($list[3]['hostname']);
        }

        return $domain;
    }

    /**
     * -------------------------------
     * TRANSFERS
     * -------------------------------
     */
    public function transferDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        $contactId = $this->createOrUpdateContact($c);

        $params = [
            'domainname' => $domain->getName(),
            'authcode'   => $domain->getEpp(),
            'period'     => '1Y',
            'owner_id'   => $contactId,
        ];

        $data = $this->call('/domain/transfer/request', 'POST', $params);

        return isset($data['transfer'][0]['status'])
            && $data['transfer'][0]['status'] === 'REQUESTED';
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $d = $this->getDomainDetails($domain);
        return $d->getEpp();
    }

    public function lock(Registrar_Domain $domain)
    {
        // Freenom does not support explicit lock control
        return false;
    }


public function unlock(Registrar_Domain $domain)
{
    return false;
}

public function enablePrivacyProtection(Registrar_Domain $domain)
{
    throw new Registrar_Exception('Freenom API does not support enabling WHOIS privacy.');
}

public function disablePrivacyProtection(Registrar_Domain $domain)
{
    throw new Registrar_Exception('Freenom API does not support disabling WHOIS privacy.');
}

    public function deleteDomain(Registrar_Domain $domain): never
    {
        throw new Registrar_Exception("Freenom does not allow deleting domains directly.");
    }

    /**
     * -------------------------------
     * CONTACT MANAGEMENT
     * -------------------------------
     */

    private function createOrUpdateContact(Registrar_Domain_Contact $c)
    {
        $params = [
            'contact_firstname'   => $c->getFirstName(),
            'contact_lastname'    => $c->getLastName(),
            'contact_address'     => $c->getAddress1(),
            'contact_city'        => $c->getCity(),
            'contact_zipcode'     => $c->getZip(),
            'contact_statecode'   => $c->getState() ?: 'NA',
            'contact_countrycode' => $c->getCountry(),
            'contact_phone'       => '+' . $c->getTelCc() . '-' . $c->getTel(),
            'contact_email'       => $c->getEmail(),
        ];

        $data = $this->call('/contact/register', 'PUT', $params);

        return $data['contact'][0]['contact_id'];
    }
}
