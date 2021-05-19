<?php

namespace Acme\Console\Certificate;

use Acme\Editor\CertificateEditor;
use Acme\Entity\Certificate;
use Concrete\Core\Console\Command;
use Concrete\Core\Error\ErrorList\ErrorList;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class EditCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Edit an existing Certificate entity.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:certificate:edit
    {certificate : The the ID of an existing Certificate}
    {--d|domains=* : Replace the domains in the certificate - use IDs or host names}
    {--p|primary= : Change the primary domain for the certificate}
    {--a|add=* : Add domains to the certificate - use IDs or host names}
    {--r|remove=* : Remove domains to the certificate - use IDs or host names}
    {--disabled= : 1 to disable the certificate, 0 to enable it}
EOT
    ;

    public function handle(CertificateEditor $certificateEditor, EntityManagerInterface $em)
    {
        $id = $this->input->getArgument('certificate');
        if ($id !== (string) (int) $id) {
            $this->output->error("Please specify an integer number for the 'certificate' argument");

            return 1;
        }
        $certificate = $em->find(Certificate::class, (int) $id);
        if ($certificate === null) {
            $this->output->error("Unable to find a certificate with ID {$id}");

            return 1;
        }
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $data = [];
        if ($this->input->getOption('domains') !== []) {
            $data['domains'] = $this->input->getOption('domains');
        }
        if ($this->input->getOption('primary') !== null) {
            $data['primaryDomain'] = $this->input->getOption('primary');
        }
        if ($this->input->getOption('add') !== []) {
            $data['addDomains'] = $this->input->getOption('add');
        }
        if ($this->input->getOption('remove') !== []) {
            $data['removeDomains'] = $this->input->getOption('remove');
        }
        if ($this->input->getOption('disabled') !== null) {
            $data['disabled'] = $this->input->getOption('disabled');
        }
        $updated = $certificateEditor->edit(
            $certificate,
            $data,
            $errors
        );
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }
        if (!$updated) {
            return 1;
        }

        $this->output->writeln('The certificate has been updated.');

        return 0;
    }
}
