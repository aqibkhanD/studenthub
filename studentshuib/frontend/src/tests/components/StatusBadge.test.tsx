import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { StatusBadge } from '@/components/ui';
import type { SubmissionStatus } from '@/types';

describe('StatusBadge', () => {
  it('renders "Submitted" label for submitted status', () => {
    render(<StatusBadge status="submitted" />);
    expect(screen.getByText('Submitted')).toBeInTheDocument();
  });

  it('renders "In Review" label for in_review status', () => {
    render(<StatusBadge status="in_review" />);
    expect(screen.getByText('In Review')).toBeInTheDocument();
  });

  it('renders "Approved" label for approved status', () => {
    render(<StatusBadge status="approved" />);
    expect(screen.getByText('Approved')).toBeInTheDocument();
  });

  it('renders "Rejected" label for rejected status', () => {
    render(<StatusBadge status="rejected" />);
    expect(screen.getByText('Rejected')).toBeInTheDocument();
  });

  it('renders "Draft" label for draft status', () => {
    render(<StatusBadge status="draft" />);
    expect(screen.getByText('Draft')).toBeInTheDocument();
  });

  it('renders "Action Required" label for action_required status', () => {
    render(<StatusBadge status="action_required" />);
    expect(screen.getByText('Action Required')).toBeInTheDocument();
  });

  it('renders as a span element', () => {
    render(<StatusBadge status="submitted" />);
    const badge = screen.getByText('Submitted');
    expect(badge.tagName.toLowerCase()).toBe('span');
  });

  const allStatuses: SubmissionStatus[] = [
    'draft', 'submitted', 'routed', 'in_review', 'action_required',
    'escalated', 'approved', 'rejected', 'returned', 'completed', 'cancelled',
  ];

  it.each(allStatuses)('renders without crashing for status "%s"', (status) => {
    const { container } = render(<StatusBadge status={status} />);
    expect(container.firstChild).not.toBeNull();
  });
});
