<?php

namespace GreenCheap\Twig;

use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Templating\StreamingEngineInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;
use Twig\Environment;
use Twig\Error\LoaderError;

class TwigEngine implements EngineInterface, StreamingEngineInterface
{
    protected $environment;
    protected $parser;

    /**
     * Constructor.
     *
     * @param Environment $environment
     * @param TemplateNameParserInterface $parser
     */
    public function __construct(Environment $environment, TemplateNameParserInterface $parser)
    {
        $this->environment = $environment;
        $this->parser = $parser;
    }

    /**
     * {@inheritdoc}
     */
    public function render($name, array $parameters = [])
    {
        return $this->load($name)->render($parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function stream($name, array $parameters = [])
    {
        $this->load($name)->display($parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        try {
            $this->environment->getLoader()->getSource((string) $name);
        } catch (LoaderError $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($name)
    {
        $template = $this->parser->parse($name);

        return 'twig' === $template->get('engine');
    }

    /**
     * Loads the given template.
     *
     * @param string|TemplateReferenceInterface|\Twig_Template $name
     * @return \Twig_TemplateInterface
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    protected function load($name)
    {
        try {
            return $this->environment->loadTemplate((string) $name);
        } catch (LoaderError $e) {
            throw new \InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
