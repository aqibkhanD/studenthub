'use client';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { studentApi } from '@/lib/api';
import { Card, CardBody, Spinner, EmptyState } from '@/components/ui';
import { CATEGORY_LABELS } from '@/types';
import type { FormType, FormCategory } from '@/types';
import Link from 'next/link';
import { FileText, ChevronRight, Clock } from 'lucide-react';

export default function FormCataloguePage() {
  const [activeCategory, setActiveCategory] = useState<string>('');

  const { data, isLoading } = useQuery({
    queryKey: ['form-types'],
    queryFn:  () => studentApi.formTypes(),
    select:   (res) => res.data.form_types as FormType[],
  });

  const formTypes = data ?? [];
  // Dedupe categories without iterating a Set — avoids tsconfig's
  // downlevelIteration requirement. Equivalent to [...new Set(...)] for
  // this size of list (~10 form types).
  const categories = formTypes
    .map(f => f.category)
    .filter((category, index, allCategories) => allCategories.indexOf(category) === index);
  const filtered   = activeCategory ? formTypes.filter(f => f.category === activeCategory) : formTypes;

  return (
    <div className="p-6 max-w-4xl mx-auto space-y-6">
      <div>
        <h1 className="text-xl font-bold text-gray-900">Submit a Request</h1>
        <p className="text-sm text-gray-500 mt-0.5">Choose the type of request you want to submit</p>
      </div>

      {/* Category filter */}
      {categories.length > 0 && (
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => setActiveCategory('')}
            className={`px-3 py-1.5 rounded-full text-xs font-medium transition-colors ${!activeCategory ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
          >
            All
          </button>
          {categories.map((cat) => (
            <button
              key={cat}
              onClick={() => setActiveCategory(cat)}
              className={`px-3 py-1.5 rounded-full text-xs font-medium transition-colors ${activeCategory === cat ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
            >
              {CATEGORY_LABELS[cat as FormCategory]}
            </button>
          ))}
        </div>
      )}

      {isLoading ? (
        <div className="flex justify-center py-16"><Spinner /></div>
      ) : filtered.length === 0 ? (
        <EmptyState title="No form types available" icon={FileText} />
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {filtered.map((ft) => (
            <Link key={ft.id} href={`/forms/${ft.slug}`}>
              <Card className="hover:shadow-md hover:border-brand-100 transition-all cursor-pointer h-full">
                <CardBody className="flex items-start justify-between gap-3">
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-semibold text-gray-900">{ft.name}</div>
                    <div className="text-xs text-gray-400 mt-1">
                      {CATEGORY_LABELS[ft.category]} · {ft.department?.name}
                    </div>
                    {ft.instructions && (
                      <p className="text-xs text-gray-500 mt-2 line-clamp-2">{ft.instructions}</p>
                    )}
                    <div className="flex items-center gap-1 mt-2 text-xs text-gray-400">
                      <Clock className="w-3 h-3" />
                      <span>SLA: {ft.sla_hours}h</span>
                      {ft.requires_documents && <span className="ml-2 text-orange-500">· Documents required</span>}
                      {ft.allow_anonymous && <span className="ml-2 text-purple-500">· Anonymous OK</span>}
                    </div>
                  </div>
                  <ChevronRight className="w-4 h-4 text-gray-300 shrink-0 mt-1" />
                </CardBody>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
