<?php

namespace servd\AssetStorage\StaticCache\Twig;

use Craft;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;
use Twig\Node\NodeOutputInterface;

class IncludeNode extends Node implements NodeOutputInterface
{

    private static $_blockCount = 1;

    public function __construct(AbstractExpression $expr, ?AbstractExpression $variables, bool $only, bool $ignoreMissing, $defaultBody, int $lineno, string $tag = null)
    {
        $nodes = ['expr' => $expr];
        if (null !== $defaultBody) {
            $nodes['defaultBody'] = $defaultBody;
        }
        if (null !== $variables) {
            $nodes['variables'] = $variables;
        }

        parent::__construct($nodes, ['only' => (bool) $only, 'ignore_missing' => (bool) $ignoreMissing], $lineno, $tag);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        $compiler->write('if( \servd\AssetStorage\Plugin::$plugin->getSettings()->disableDynamic || !\Craft::$app->request->getIsSiteRequest() ){' . "\n")->indent();
        $this->standardInclude($compiler);
        $compiler->outdent()->write('} else {' . "\n")->indent();
        $compiler->write('if(getenv("SERVD_ESI_ENABLED") === "true" && ($_SERVER["HTTP_X_SERVD_CACHE"] ?? "0") === "1"){' . "\n")->indent();
        //NOTE: Use the below line to test ESI output when working locally
        //$compiler->write('if(true){' . "\n")->indent();
        $this->esiInclude($compiler);
        $compiler->outdent()->write('} else {' . "\n")->indent();
        $this->ajaxInclude($compiler);
        $compiler->outdent()->write('}' . "\n");
        $compiler->outdent()->write('}' . "\n");
    }

    protected function standardInclude(Compiler $compiler)
    {
        if ($this->getAttribute('ignore_missing')) {
            $template = $compiler->getVarName();

            $compiler
                ->write(sprintf("$%s = null;\n", $template))
                ->write("try {\n")
                ->indent()
                ->write(sprintf('$%s = ', $template));

            $this->addGetTemplate($compiler);

            $compiler
                ->raw(";\n")
                ->outdent()
                ->write("} catch (LoaderError \$e) {\n")
                ->indent()
                ->write("// ignore missing template\n")
                ->outdent()
                ->write("}\n")
                ->write(sprintf("if ($%s) {\n", $template))
                ->indent()
                ->write(sprintf('$%s->display(', $template));
            $this->addTemplateArguments($compiler);
            $compiler
                ->raw(");\n")
                ->outdent()
                ->write("}\n");
        } else {
            $this->addGetTemplate($compiler);
            $compiler->raw('->display(');
            $this->addTemplateArguments($compiler);
            $compiler->raw(");\n");
        }
    }

    protected function ajaxInclude(Compiler $compiler)
    {
        $n = self::$_blockCount++;
        $namespace = $compiler->getVarName();

        $compiler->write('$' . $namespace . 'template = base64_encode(');
        $compiler->subcompile($this->getNode('expr'));
        $compiler->write(');' . "\n");
        $compiler->write('$' . $namespace . 'fullContext = ');
        $this->addTemplateArguments($compiler, true);
        $compiler->write(';' . "\n");

        $compiler->write('$' . $namespace . 'serializableContext = \servd\AssetStorage\StaticCache\Twig\IncludeNode::cleanContextArray($' . $namespace . 'fullContext, true);' . "\n");
        $compiler->write('$' . $namespace . 'finalArguments = base64_encode(gzcompress(serialize($' . $namespace . 'serializableContext)));' . "\n");
        $compiler->write('$' . $namespace . 'ignoreMissing = "' . ($this->getAttribute('ignore_missing') ? 'true' : 'false') . '";' . "\n");
        $compiler->write('$' . $namespace . 'siteId = \Craft::$app->getSites()->getCurrentSite()->id;' . "\n");

        //Dynamically generated PHP setting static properties on arbitrary classes to track state across the request lifecycle.
        //Some developers might get  angry about that.
        $compiler->write('\servd\AssetStorage\StaticCache\StaticCache::$dynamicBlocksAdded[] = true;');
        $compiler->write('echo "<div id=\"dynamic-block-" . mt_rand() . "\" class=\"dynamic-block\" ' .
            'data-site=\"$' . $namespace . 'siteId\" ' .
            'data-template=\"$' . $namespace . 'template\" ' .
            'data-args=\"$' . $namespace . 'finalArguments\" ' .
            'data-ignore-missing=\"$' . $namespace . 'ignoreMissing\">";' . "\n");
        if ($this->hasNode('defaultBody')) {
            $compiler->subcompile($this->getNode('defaultBody'));
        }
        $compiler->write('echo "</div>";' . "\n");
    }

    protected function esiInclude(Compiler $compiler)
    {
        $n = self::$_blockCount++;
        $namespace = $compiler->getVarName();

        $compiler->write('$' . $namespace . 'template = ');
        $compiler->subcompile($this->getNode('expr'));
        $compiler->write(';' . "\n");
        $compiler->write('$' . $namespace . 'fullContext = ');
        $this->addTemplateArguments($compiler, true);
        $compiler->write(';' . "\n");

        $compiler->write('$' . $namespace . 'serializableContext = \servd\AssetStorage\StaticCache\Twig\IncludeNode::cleanContextArray($' . $namespace . 'fullContext, true);' . "\n");
        $compiler->write('$' . $namespace . 'finalArguments = $' . $namespace . 'serializableContext;' . "\n");
        $compiler->write('$' . $namespace . 'ignoreMissing = "' . ($this->getAttribute('ignore_missing') ? 'true' : 'false') . '";' . "\n");
        $compiler->write('$' . $namespace . 'siteId = \Craft::$app->getSites()->getCurrentSite()->id;' . "\n");

        $compiler->write('\servd\AssetStorage\StaticCache\StaticCache::$dynamicBlocksAdded[] = true;');
        $compiler->write('\servd\AssetStorage\StaticCache\StaticCache::$esiBlocks[] = [' .
            '"id" => "dynamic-block-' . $n . '", ' .
            '"template" => $' . $namespace . 'template, ' .
            '"args" =>  $' . $namespace . 'finalArguments, ' .
            '"siteId" =>  $' . $namespace . 'siteId, ' .
            '];');

        $compiler->write('echo "<div id=\"dynamic-block-' . $n . '\" />";' . "\n");

        if ($this->hasNode('defaultBody')) {
            $compiler->subcompile($this->getNode('defaultBody'));
        }
        $compiler->write('echo "</div>";' . "\n");
    }

    protected function addGetTemplate(Compiler $compiler)
    {
        $compiler
            ->write('$this->loadTemplate(')
            ->subcompile($this->getNode('expr'))
            ->raw(', ')
            ->repr($this->getTemplateName())
            ->raw(', ')
            ->repr($this->getTemplateLine())
            ->raw(')');
    }

    protected function addTemplateArguments(Compiler $compiler, $allowFullContext = false)
    {

        if (!$this->hasNode('variables')) {
            $compiler->raw((false === $this->getAttribute('only') && $allowFullContext) ? '$context' : '[]');
        } elseif ($allowFullContext && false === $this->getAttribute('only')) {
            $compiler->raw('twig_array_merge($context, ');
            $compiler->subcompile($this->getNode('variables'));
            $compiler->raw(')');
        } else {
            $compiler->raw('twig_to_array(');
            $compiler->subcompile($this->getNode('variables'));
            $compiler->raw(')');
        }
    }

    public static function cleanContextArray($a, $isRoot)
    {
        $twigGlobals = array_keys(Craft::$app->getView()->getTwig()->getGlobals());
        $cleaned = [];
        foreach ($a as $key => $el) {
            //Don't include Craft Globals if we're workign on the root context array
            if ($isRoot && in_array($key, $twigGlobals)) {
                continue;
            }
            //Scalars are ok
            if (is_scalar($el) || is_bool($el) || is_null($el)) {
                $cleaned[$key] = $el;
                continue;
            }
            //Arrays need to be recursed into
            if (is_array($el)) {
                $cleaned[$key] = static::cleanContextArray($el, false);
                continue;
            }
            //The only objects we want are subclasses of Craft's Element
            //We only store a reference to these. Also works with custom element types
            //Exclude any objects which have no id set (not yet saved to the database) - these can't be rehydrated
            if (is_object($el) && is_subclass_of($el, \craft\base\Element::class) && !is_null($el->id)) {
                $cleaned[$key] = [
                    'type' => get_class($el),
                    'id' => $el->id,
                ];
                continue;
            }
        }
        return $cleaned;
    }
}
