import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { stageColor } from '@/components/work/stage-badge';
import type { PipelineStage } from '@/types';

/**
 * Part-to-whole across the four workflow stages, as one horizontal stacked bar.
 *
 * Design notes, so later edits don't undo them:
 *  - Stages are an ORDINAL scale, so they take one hue light -> dark, not four
 *    categorical hues (see --stage-* in app.css).
 *  - Segments are separated by a 2px gap in the surface colour, never a stroke.
 *  - Interior segments carry no inline label — there is no free end to put one
 *    on — so the legend below doubles as the table view: every count is
 *    readable as text without hovering. The tooltip only adds the share.
 */
export default function PipelineBar({ stages }: { stages: PipelineStage[] }) {
    const total = stages.reduce((sum, stage) => sum + stage.count, 0);
    const visible = stages.filter((stage) => stage.count > 0);

    return (
        <div className="space-y-4">
            {total === 0 ? (
                <div className="h-4 w-full rounded-[4px]" style={{ background: 'var(--viz-track)' }} />
            ) : (
                <TooltipProvider delayDuration={100}>
                    {/* 2px gap = the surface doing the separating. */}
                    <div className="flex h-4 w-full gap-[2px]" role="img" aria-label={`Tasks by stage, ${total} in total`}>
                        {visible.map((stage, index) => (
                            <Tooltip key={stage.value}>
                                <TooltipTrigger asChild>
                                    {/* Hit target spans the full bar height, well past the mark itself. */}
                                    <button
                                        type="button"
                                        className="focus-visible:ring-ring h-full min-w-[3px] cursor-default focus-visible:ring-2 focus-visible:outline-hidden"
                                        style={{
                                            flexGrow: stage.count,
                                            flexBasis: 0,
                                            background: stageColor[stage.value],
                                            // 4px rounded data-ends; interior joins stay square.
                                            borderTopLeftRadius: index === 0 ? 4 : 0,
                                            borderBottomLeftRadius: index === 0 ? 4 : 0,
                                            borderTopRightRadius: index === visible.length - 1 ? 4 : 0,
                                            borderBottomRightRadius: index === visible.length - 1 ? 4 : 0,
                                        }}
                                    >
                                        <span className="sr-only">
                                            {stage.label}: {stage.count}
                                        </span>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {stage.label}: {stage.count} of {total} ({Math.round((stage.count / total) * 100)}%)
                                </TooltipContent>
                            </Tooltip>
                        ))}
                    </div>
                </TooltipProvider>
            )}

            {/* Legend is always present, and carries every value as text. */}
            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4">
                {stages.map((stage) => (
                    <div key={stage.value} className="flex items-start gap-2">
                        <span aria-hidden className="mt-1 size-2.5 shrink-0 rounded-full" style={{ background: stageColor[stage.value] }} />
                        <div className="min-w-0">
                            <dt className="text-muted-foreground truncate text-xs">{stage.label}</dt>
                            <dd className="text-lg leading-tight font-semibold">{stage.count}</dd>
                        </div>
                    </div>
                ))}
            </dl>
        </div>
    );
}
