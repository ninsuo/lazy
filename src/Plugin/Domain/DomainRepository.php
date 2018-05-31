<?php

namespace Lazy\Plugin\Domain;

use Lazy\Core\Base\BaseService;

class DomainRepository extends BaseService
{
    /**
     * @return Domains
     */
    public function getDoamins()
    {
        $domains = new Domains();

        $domains->domains = array_filter(
            array_map(function($elem) {
                $domain = substr(basename($elem), 3);
                if (preg_match('/^[0-9\.]+$/', $domain) || in_array($domain, ['empty', 'local', 'root'])) {
                    return false;
                }

                return $domain;
            }, glob('/etc/bind/db.*'))
        );

        $file = '/etc/bind/db.' . $this->getArpa();

        if (is_file($file)) {
            $primary = $this->exec("cat :file | grep IN | grep SOA | cut -d '\t' -f 4 | cut -d ' ' -f 1", [
                'file' => $file,
            ]);

            $domains->primary = substr($primary->stdout, 0, -1);
        }

        return $domains;
    }

    public function getArpa()
    {
        $ip = explode('.', $this->getParameter('server_ip'));
        return $ip[2].'.'.$ip[1].'.'.$ip[0];
    }
}
