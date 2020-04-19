<?php

/*
 * This file is part of the NelmioApiDocBundle.
 *
 * (c) Nelmio <hello@nelm.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Command;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class DumpCommand.
 */
class DumpCommand extends ContainerAwareCommand
{
    /**
     * @var array
     */
    protected $enums = [];

    /**
     * @var array
     */
    protected $availableFormats = array('markdown', 'json', 'html');

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setDescription('')
            ->addOption(
                'format', '', InputOption::VALUE_REQUIRED,
                'Output format like: '.implode(', ', $this->availableFormats),
                $this->availableFormats[0]
            )
            ->addOption('view', '', InputOption::VALUE_OPTIONAL, '', ApiDoc::DEFAULT_VIEW)
            ->addOption('no-sandbox', '', InputOption::VALUE_NONE)
            ->setName('eliberty:api:doc:generate')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->createdEnums();

        $format = $input->getOption('format');
        $view   = $input->getOption('view');

        if (!$input->hasOption('format') || in_array($format, array('json'))) {
            $formatter = $this->getContainer()->get('nelmio_api_doc.formatter.simple_formatter');
        } else {
            if (!in_array($format, $this->availableFormats)) {
                throw new \RuntimeException(sprintf('Format "%s" not supported.', $format));
            }

            $formatter = $this->getContainer()->get('eliberty_api_doc.formatter.file_formatter');
        }

        if ($input->getOption('no-sandbox') && 'html' === $format) {
            $formatter->setEnableSandbox(false);
        }

        $formatter->setEnums($this->enums);

        if ('html' === $format && method_exists($this->getContainer(), 'enterScope')) {
            $this->getContainer()->enterScope('request');
            $this->getContainer()->set('request', new Request(), 'request');
        }
        $extractorService = $this->getContainer()->get('nelmio_api_doc.extractor.api_doc_extractor');
        $extractedDoc = $extractorService->all($view);
        $formattedDoc = $formatter->format($extractedDoc);

        $output->writeln($formattedDoc);
    }

    /**
     *
     */
    public function createdEnums()
    {
        $container = $this->getContainer();
        $engine = $container->get('templating');
        $reader = $container->get('annotation_reader');
        $rootDir = $container->getParameter('kernel.root_dir');
        $fs = new Filesystem();
        $baseEnumPath = 'Eliberty\\RedpillBundle\\Enum\\';

        $listEnum = scandir($rootDir.'/../src/Eliberty/RedpillBundle/Enum');
        foreach ($listEnum as $filename) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = pathinfo($filename, PATHINFO_FILENAME);
            if ($ext === 'php') {
                $class = $baseEnumPath.$filename;
                $strFilename = strtolower($filename);
                $propertyDescription = $reader->getClassAnnotations(new \ReflectionClass($class));
                if (!empty($propertyDescription)) {
                    $this->enums[] = $strFilename;
                    $dataFilters = $engine->render('ElibertyApiBundle:nelmio:enums.html.twig', ['data' => $propertyDescription[0]]);
                    $fs->mkdir($rootDir.'/../apidoc/docs/metadata//enums/'.$strFilename);
                    $fs->dumpFile($rootDir.'/../apidoc/docs/metadata/enums/'.$strFilename.'/definition.html', $dataFilters);
                }
            }
        }
    }
}
