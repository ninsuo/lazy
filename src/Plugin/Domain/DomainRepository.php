<?php

namespace Lazy\Plugin\Domain;

use Lazy\Core\Base\BaseService;

class DomainRepository extends BaseService
{
    /**
     * @return Domains
     */
    public function getDomains()
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

    private function getArpa()
    {
        $ip = explode('.', $this->getParameter('server_ip'));
        return $ip[2].'.'.$ip[1].'.'.$ip[0];
    }

    public function createDomain($name, $email)
    {
        $domains = $this->getRepository()->getDomains();
        if (array_key_exists($name, $domains->domains)) {
            $this->error('Domain %s already exists!', $name);
            throw new StopExecutionException();
        }



        /*
{% autoescape false %}
;
; BIND data file for {{ name }}
;
$ORIGIN {{ domain }}.
$TTL	86400
@	IN	SOA	ns.{{ domain }}. {{ email }}. (
			{{ timestamp }}	; Serial
			10800		; Refresh
			3600		; Retry
			604800		; Expire
			10800 )	; Negative Cache TTL
;
@	IN	NS	ns.{{ domain }}.
@	IN	TXT	"v=spf1 +a +mx +ip4:{{ server_ip }} -all"
@	IN	A	{{ server_ip }}
*	IN	A	{{ server_ip }}

{% endautoescape %}

         */
    }
}
