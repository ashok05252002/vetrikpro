import { Button } from '@/components/ui/button';
import TestingPointsView, { type TestingPointsProps } from '@/components/work/testing-points-view';
import ProjectWorkspaceLayout from '@/layouts/project/workspace-layout';
import type { TestingCounts } from '@/types';
import { Link } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';

/**
 * The project's Testing tab: its bugs, right here in the project, with the
 * same rights as in the Testing module. Test runs live in the module.
 */
export default function ProjectTesting({ counts, ...props }: TestingPointsProps & { counts: TestingCounts }) {
    return (
        <ProjectWorkspaceLayout
            project={props.project}
            tab="testing"
            actions={
                <Button asChild size="sm" variant="outline">
                    <Link href={route('testing.runs.index', props.project.id)}>
                        <FlaskConical className="size-4" /> Test runs{counts.runs ? ` (${counts.runs})` : ''}
                    </Link>
                </Button>
            }
        >
            <TestingPointsView url={route('projects.testing', props.project.id)} {...props} />
        </ProjectWorkspaceLayout>
    );
}
