<?php

namespace Lazy\Plugin\Domain;

use Lazy\Core\Base\BaseHandler;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;

class DomainHandler extends BaseHandler
{
    public function handleList(Args $args, IO $io)
    {
        $domains = $this->getRepository()->getDoamins();

        $table = new Table();
        $table->setHeaderRow(['Domain Name', 'Primary']);
        foreach ($domains->domains as $domain) {
            $table->addRow([
                $domain,
                $domain === $domains->primary ? '✅' : '❌',
            ]);
        }

        $table->render($io);
    }

    public function handleAdd(Args $args, IO $io)
    {

    }

    public function handleRemove(Args $args, IO $io)
    {

    }

    public function handleBackup(Args $args, IO $io)
    {

    }

    public function handleRestore(Args $args, IO $io)
    {

    }

    public function handleListBackups(Args $args, IO $io)
    {

    }

    public function handleRemoveBackup(Args $args, IO $io)
    {

    }

    /**
     * @return DomainRepository
     */
    private function getRepository()
    {
        return $this->container['domain.repository'];
    }
}