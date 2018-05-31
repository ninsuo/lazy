<?php

namespace Lazy\Plugin\Domain;

use Lazy\Core\Base\BaseHandler;
use Lazy\Core\Exception\StopExecutionException;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Regex;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;

class DomainHandler extends BaseHandler
{
    public function handleList(Args $args, IO $io)
    {
        $domains = $this->getRepository()->getDomains();

        $table = new Table();
        $table->setHeaderRow(['Domain Name', 'Primary']);
        foreach ($domains->domains as $domain) {
            $table->addRow([
                $domain,
                $domain === $domains->primary ? '<green>y</green>' : '<red>no</red>',
            ]);
        }

        $table->render($io);
    }

    public function handleEnroll(Args $args, IO $io)
    {
        $domain = $args->getArgument('name');

        $email = $args->getArgument('email');
        if (is_null($email)) {
            $email = sprintf('admin@%s', $domain);
        }

        $this->validate('domain', $domain, new Regex('!^[a-zA-Z0-9\.]+$!'));
        $this->validate('email', $email, new Email());

        $domain = trim(mb_strtolower($args->getArgument('name')), '.');
        $email = preg_replace("/[^a-z0-9]/", '.', $email);

        $this->getRepository()->createDomain($domain, $email);
    }

    public function handleEdit(Args $args, IO $io)
    {
        $this->info($this->prompt("Hello world?", ['yes', 'no']));
    }

    /**
     * @return DomainRepository
     */
    private function getRepository()
    {
        return $this->container['domain.repository'];
    }
}