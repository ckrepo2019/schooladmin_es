import { Card, CardContent, CardHeader, CardTitle } from '../../../../../../components/ui/card';
import { Users, GraduationCap, BookOpen } from 'lucide-react';
import { Progress } from '../../../../../../components/ui/progress';

const toNumber = (value) => Number(value) || 0;
const formatNumber = (value) => new Intl.NumberFormat('en-US').format(toNumber(value));
const formatPercent = (value) => `${value.toFixed(1)}%`;

export function EnrollmentStats({ summary, breakdown }) {
  if (!summary) {
    return null;
  }

  const { gradeSchool, shs, college } = summary;

  const levelStats = [
    {
      key: 'gradeschool',
      title: 'Grade School',
      icon: BookOpen,
      watermark: 'GRADE',
      total: toNumber(gradeSchool?.total),
      enrolled: toNumber(gradeSchool?.enrolled),
      late: toNumber(gradeSchool?.late_enrollment),
      dropped: toNumber(gradeSchool?.dropped_out),
      withdrawn: toNumber(gradeSchool?.withdrawn),
      breakdown: breakdown?.gradeSchoolByLevel || [],
      colors: {
        text: 'text-emerald-600',
        bg: 'bg-emerald-500/10',
        bar: 'bg-emerald-500',
      },
    },
    {
      key: 'shs',
      title: 'Senior High School',
      icon: Users,
      watermark: 'SHS',
      total: toNumber(shs?.total),
      enrolled: toNumber(shs?.enrolled),
      late: toNumber(shs?.late_enrollment),
      dropped: toNumber(shs?.dropped_out),
      withdrawn: toNumber(shs?.withdrawn),
      breakdown: breakdown?.shsByStrand || [],
      colors: {
        text: 'text-violet-600',
        bg: 'bg-violet-500/10',
        bar: 'bg-violet-500',
      },
    },
    {
      key: 'college',
      title: 'College',
      icon: GraduationCap,
      watermark: 'COLLEGE',
      total: toNumber(college?.total),
      enrolled: toNumber(college?.enrolled),
      late: toNumber(college?.late_enrollment),
      dropped: toNumber(college?.dropped_out),
      withdrawn: toNumber(college?.withdrawn),
      breakdown: breakdown?.collegeByYearLevel || [],
      colors: {
        text: 'text-orange-600',
        bg: 'bg-orange-500/10',
        bar: 'bg-orange-500',
      },
    },
  ];

  const totalEnrolled = levelStats.reduce((sum, level) => sum + level.enrolled, 0);
  const totalStudents = levelStats.reduce((sum, level) => sum + level.total, 0);
  const enrollmentRate = totalStudents > 0 ? (totalEnrolled / totalStudents) * 100 : 0;
  const otherStatuses = Math.max(totalStudents - totalEnrolled, 0);

  const statusSections = levelStats.map((level) => ({
    key: level.key,
    title: `${level.title} Status`,
    watermark: level.key === 'gradeschool' ? 'GS' : level.key === 'shs' ? 'SHS' : 'COL',
    total: level.total,
    items: [
      { label: 'Enrolled', value: level.enrolled, variant: 'success' },
      { label: 'Late Enrollment', value: level.late, variant: 'warning' },
      { label: 'Dropped Out', value: level.dropped, variant: 'danger' },
      { label: 'Withdrawn', value: level.withdrawn, variant: 'secondary' },
    ],
  }));

  return (
    <div className="space-y-6">
      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2" data-watermark="TOTAL">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Total Students</CardTitle>
            <p className="text-xs text-muted-foreground">All levels combined</p>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-4">
              <div className="space-y-1">
                <div className="text-3xl font-semibold tracking-tight">
                  {formatNumber(totalStudents)}
                </div>
                <p className="text-xs text-muted-foreground">Total students recorded</p>
              </div>
              <div className="rounded-lg border bg-muted/30 px-3 py-2 text-right">
                <div className="text-xs text-muted-foreground">Currently enrolled</div>
                <div className="text-lg font-semibold">{formatNumber(totalEnrolled)}</div>
              </div>
            </div>
            <div className="space-y-2">
              <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Enrollment rate</span>
                <span className="font-medium">{formatPercent(enrollmentRate)}</span>
              </div>
              <Progress value={enrollmentRate} className="h-2" />
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
              <div className="flex items-center justify-between rounded-md border bg-background/50 px-3 py-2 text-xs">
                <span className="text-muted-foreground">Enrolled</span>
                <span className="font-semibold">{formatNumber(totalEnrolled)}</span>
              </div>
              <div className="flex items-center justify-between rounded-md border bg-background/50 px-3 py-2 text-xs">
                <span className="text-muted-foreground">Other statuses</span>
                <span className="font-semibold">{formatNumber(otherStatuses)}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card data-watermark="LEVELS">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Level Distribution</CardTitle>
            <p className="text-xs text-muted-foreground">Share of total students</p>
          </CardHeader>
          <CardContent className="space-y-3">
            {levelStats.map((level) => {
              const percentage = totalStudents > 0 ? (level.total / totalStudents) * 100 : 0;
              return (
                <div key={level.key} className="space-y-1">
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-muted-foreground">{level.title}</span>
                    <span className="font-medium">
                      {formatNumber(level.total)} ({formatPercent(percentage)})
                    </span>
                  </div>
                  <Progress value={percentage} className="h-2" indicatorClassName={level.colors.bar} />
                </div>
              );
            })}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {levelStats.map((level) => {
          const levelRate = level.total > 0 ? (level.enrolled / level.total) * 100 : 0;
          return (
            <Card
              key={level.key}
              className="transition-colors hover:bg-muted/30"
              data-watermark={level.watermark}
            >
              <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
                <div className="flex items-center gap-3">
                  <div
                    className={`flex h-10 w-10 items-center justify-center rounded-lg ${level.colors.bg}`}
                  >
                    <level.icon className={`h-4 w-4 ${level.colors.text}`} />
                  </div>
                  <div className="space-y-1">
                    <CardTitle className="text-sm font-medium">{level.title}</CardTitle>
                    <p className="text-xs text-muted-foreground">
                      {formatNumber(level.enrolled)} enrolled
                    </p>
                  </div>
                </div>
                <div className="text-right">
                  <div className="text-2xl font-semibold">{formatNumber(level.total)}</div>
                  <p className="text-xs text-muted-foreground">Total students</p>
                </div>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="space-y-1">
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-muted-foreground">Enrollment rate</span>
                    <span className="font-medium">{formatPercent(levelRate)}</span>
                  </div>
                  <Progress value={levelRate} className="h-2" indicatorClassName={level.colors.bar} />
                </div>
                <div className="grid grid-cols-2 gap-2 text-xs">
                  {[
                    { label: 'Enrolled', value: level.enrolled },
                    { label: 'Late', value: level.late },
                    { label: 'Dropped', value: level.dropped },
                    { label: 'Withdrawn', value: level.withdrawn },
                  ].map((item) => (
                    <div
                      key={item.label}
                      className="flex items-center justify-between rounded-md border bg-muted/30 px-2 py-1"
                    >
                      <span className="text-muted-foreground">{item.label}</span>
                      <span className="font-medium text-foreground">
                        {formatNumber(item.value)}
                      </span>
                    </div>
                  ))}
                </div>
                {level.breakdown.length > 0 ? (
                  <div className="space-y-1">
                    {level.breakdown.slice(0, 3).map((item, idx) => (
                      <div key={idx} className="flex items-center justify-between text-xs">
                        <span className="text-muted-foreground truncate max-w-[180px]">
                          {item.level_name || item.strand_name || item.course_name}
                          {item.yearLevel && ` (Year ${item.yearLevel})`}
                        </span>
                        <span className="font-medium">{formatNumber(item.count)}</span>
                      </div>
                    ))}
                    {level.breakdown.length > 3 && (
                      <p className="text-xs text-muted-foreground italic">
                        +{level.breakdown.length - 3} more
                      </p>
                    )}
                  </div>
                ) : (
                  <p className="text-xs text-muted-foreground">No breakdown data yet</p>
                )}
              </CardContent>
            </Card>
          );
        })}
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {statusSections.map((section) => (
          <Card key={section.key} data-watermark={section.watermark}>
            <CardHeader className="space-y-1">
              <CardTitle className="text-sm font-medium">{section.title}</CardTitle>
              <p className="text-xs text-muted-foreground">
                {formatNumber(section.total)} total students
              </p>
            </CardHeader>
            <CardContent className="space-y-3">
              {section.items.map((item) => (
                <StatusItem
                  key={item.label}
                  label={item.label}
                  value={item.value}
                  total={section.total}
                  variant={item.variant}
                />
              ))}
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}

function StatusItem({ label, value, total, variant }) {
  const safeValue = toNumber(value);
  const safeTotal = toNumber(total);
  const percentage = safeTotal > 0 ? (safeValue / safeTotal) * 100 : 0;

  const variantColors = {
    success: 'bg-green-500',
    warning: 'bg-yellow-500',
    danger: 'bg-red-500',
    secondary: 'bg-gray-500',
  };

  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between text-sm">
        <span className="text-muted-foreground">{label}</span>
        <span className="font-medium">
          {formatNumber(safeValue)} ({formatPercent(percentage)})
        </span>
      </div>
      <Progress value={percentage} className="h-2" indicatorClassName={variantColors[variant]} />
    </div>
  );
}
