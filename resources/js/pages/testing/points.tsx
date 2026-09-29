import TestingPointsView, { type TestingPointsProps } from '@/components/work/testing-points-view';
import TestingLayout from '@/layouts/testing/testing-layout';
import type { TestingCounts } from '@/types';

/** The Testing module's view of one project's bugs. */
export default function TestingPoints({ counts, ...props }: TestingPointsProps & { counts: TestingCounts }) {
    return (
        <TestingLayout project={props.project} counts={counts} tab="points">
            <TestingPointsView url={route('testing.points.index', props.project.id)} {...props} />
        </TestingLayout>
    );
}
