<?php

declare(strict_types=1);

namespace TorStatus\Graph;

use mitoteam\jpgraph\MtJpGraph;

final class SessionBarGraphRenderer
{
    /** @var array<string, mixed> */
    private $session;

    /** @param array<string, mixed> $session */
    public function __construct(array $session)
    {
        $this->session = $session;
    }

    /** @param array{0:int,1:int,2:int,3:int} $margin */
    public function render(string $prefix, int $width, int $height, array $margin, bool $rotateLabels = false): void
    {
        MtJpGraph::load('bar');
        $graphData = GraphSessionStore::get($this->session, $prefix);

        $graph = new \Graph($width, $height, 'auto');
        $graph->SetMargin($margin[0], $margin[1], $margin[2], $margin[3]);
        $graph->SetScale('textlin');
        $graph->xaxis->SetTickLabels($graphData['labels']);
        if ($rotateLabels) {
            $graph->xaxis->SetLabelAngle(90);
        }
        $graph->title->Set($graphData['title']);
        $graph->title->SetFont(FF_FONT2, FS_BOLD);

        $bar = new \BarPlot($graphData['data']);
        $bar->SetLegend($graphData['legend']);
        $bar->SetShadow();
        $bar->value->Show();
        $bar->value->SetFormat('%d');
        $graph->Add($bar);
        $graph->Stroke();
    }
}
