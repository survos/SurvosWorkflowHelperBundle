<?php


namespace Survos\WorkflowBundle\Service;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Dumper\DumperInterface;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Dumper\StateMachineGraphvizDumper;
use Symfony\Component\Workflow\Marking;
/**
 * GraphvizDumper dumps a workflow as a graphviz file.
 *
 * You can convert the generated dot file with the dot utility (https://graphviz.org/):
 *
 *   dot -Tpng workflow.dot > workflow.png
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SurvosStateMachineGraphVizDumper extends StateMachineGraphvizDumper
{
    /**
     * {@inheritdoc}
     *
     * Dumps the workflow as a graphviz graph.
     *
     * Available options:
     *
     *  * graph: The default options for the whole graph
     *  * node: The default options for nodes (places)
     *  * edge: The default options for edges
     */
    public function dump(Definition $definition, Marking $marking = null, array $options = []): string
    {
        $places = $this->findPlaces($definition, $marking);
        $edges = $this->findEdges($definition);

        $options = array_replace_recursive(self::$defaultOptions, $options);

        return $this->startDot($options)
            .$this->addPlaces($places)
            .$this->addEdges($edges)
            .$this->endDot()
            ;
    }

    protected  function dotize(string $id): string
    {
        return $id;
    }

    /**
     * @internal
     */
    protected function findEdges(Definition $definition): array
    {
        $workflowMetadata = $definition->getMetadataStore();

        $edges = [];

        foreach ($definition->getTransitions() as $transition) {
            $attributes = [];

            $transitionName = $workflowMetadata->getMetadata('label', $transition) ?? $transition->getName();

            $labelColor = $workflowMetadata->getMetadata('color', $transition);
            if (null !== $labelColor) {
                $attributes['fontcolor'] = $labelColor;
            }
            $arrowColor = $workflowMetadata->getMetadata('arrow_color', $transition);
            if (null !== $arrowColor) {
                $attributes['color'] = $arrowColor;
            }

            foreach ($transition->getFroms() as $from) {
                foreach ($transition->getTos() as $to) {
                    $edge = [
                        'name' => $transitionName,
                        'to' => $to,
                        'attributes' => $attributes,
                    ];
                    $edges[$from][] = $edge;
                }
            }
        }

        return $edges;
    }

    /**
     * @internal
     */
    protected function addEdges(array $edges): string
    {
        $code = '';

        foreach ($edges as $id => $edges) {
            foreach ($edges as $edge) {
                $code .= sprintf(
                    "  place_%s -> place_%s [label=\"%s\" style=\"%s\"%s];\n",
                    $this->dotize($id),
                    $this->dotize($edge['to']),
                    $this->escape($edge['name']),
                    'dotted',
                    $this->addAttributes($edge['attributes'])
                );
            }
        }

        return $code;
    }


protected function addPlaces(array $places): string
{
    $code = '';

    foreach ($places as $id => $place) {
        if (isset($place['attributes']['name'])) {
            $placeName = $place['attributes']['name'];
            unset($place['attributes']['name']);
        } else {
            $placeName = $id;
        }

        if (isset($place['attributes']['shape'])) {
            $shape = $place['attributes']['shape'];
            unset($place['attributes']['shape']);
        } else {
            $shape = 'ellipse';
        }
        assert(!empty($shape), json_encode($place));

        $code .= sprintf("  place_%s [label=\"%s\", shape=%s%s];\n",
            $this->dotize($id), $this->escape($placeName), $shape, $this->addAttributes($place['attributes']));
    }
//    dd($code);

    return $code;
}

}
