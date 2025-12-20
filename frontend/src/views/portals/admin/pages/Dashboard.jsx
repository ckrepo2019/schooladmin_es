import { useMemo } from 'react'
import { Link } from 'react-router-dom'
import {
  CalendarDays,
  DollarSign,
  GraduationCap,
  Megaphone,
  TrendingDown,
  TrendingUp,
  UsersRound,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'

const hashString = (value) => {
  let hash = 0
  for (let i = 0; i < value.length; i += 1) {
    hash = (hash << 5) - hash + value.charCodeAt(i)
    hash |= 0
  }
  return Math.abs(hash)
}

const mulberry32 = (seed) => {
  let t = seed
  return () => {
    t += 0x6d2b79f5
    let r = Math.imul(t ^ (t >>> 15), 1 | t)
    r ^= r + Math.imul(r ^ (r >>> 7), 61 | r)
    return ((r ^ (r >>> 14)) >>> 0) / 4294967296
  }
}

const clamp = (value, min, max) => Math.min(max, Math.max(min, value))

const buildSparklinePoints = (values, width, height, padding) => {
  if (!Array.isArray(values) || values.length < 2) return ''
  const min = Math.min(...values)
  const max = Math.max(...values)
  const range = max - min || 1

  return values
    .map((value, index) => {
      const x = padding + (index / (values.length - 1)) * (width - padding * 2)
      const y = height - padding - ((value - min) / range) * (height - padding * 2)
      return `${x},${y}`
    })
    .join(' ')
}

function Sparkline({ data, className }) {
  const width = 120
  const height = 36
  const padding = 3
  const points = useMemo(() => buildSparklinePoints(data, width, height, padding), [data])

  if (!points) return null

  return (
    <svg
      className={className}
      viewBox={`0 0 ${width} ${height}`}
      width={width}
      height={height}
      aria-hidden='true'
    >
      <polyline
        points={points}
        fill='none'
        stroke='currentColor'
        strokeWidth='2'
        strokeLinecap='round'
        strokeLinejoin='round'
      />
    </svg>
  )
}

const buildLinePath = (values, width, height, padding) => {
  if (!Array.isArray(values) || values.length < 2) return ''
  const min = Math.min(...values)
  const max = Math.max(...values)
  const range = max - min || 1

  return values
    .map((value, index) => {
      const x = padding + (index / (values.length - 1)) * (width - padding * 2)
      const y = height - padding - ((value - min) / range) * (height - padding * 2)
      return `${index === 0 ? 'M' : 'L'} ${x} ${y}`
    })
    .join(' ')
}

function MiniLineChart({ series }) {
  const width = 520
  const height = 160
  const padding = 14

  return (
    <svg
      viewBox={`0 0 ${width} ${height}`}
      className='h-40 w-full'
      role='img'
      aria-label='Chart'
    >
      <defs>
        <linearGradient id='area-primary' x1='0' y1='0' x2='0' y2='1'>
          <stop offset='0%' stopColor='hsl(var(--primary))' stopOpacity='0.22' />
          <stop offset='100%' stopColor='hsl(var(--primary))' stopOpacity='0' />
        </linearGradient>
      </defs>

      {/* Grid */}
      {[0, 1, 2, 3].map((i) => (
        <line
          key={i}
          x1={padding}
          x2={width - padding}
          y1={padding + (i * (height - padding * 2)) / 3}
          y2={padding + (i * (height - padding * 2)) / 3}
          stroke='hsl(var(--border))'
          strokeOpacity='0.6'
          strokeWidth='1'
        />
      ))}

      {series.map((item, index) => {
        const path = buildLinePath(item.data, width, height, padding)
        if (!path) return null

        const areaPath = `${path} L ${width - padding} ${height - padding} L ${padding} ${height - padding} Z`
        const isPrimary = index === 0

        return (
          <g key={item.label}>
            {isPrimary ? (
              <path d={areaPath} fill='url(#area-primary)' stroke='none' />
            ) : null}
            <path
              d={path}
              fill='none'
              stroke={item.stroke}
              strokeWidth='2'
              strokeLinecap='round'
              strokeLinejoin='round'
            />
          </g>
        )
      })}
    </svg>
  )
}

function generateMockDashboard(selectedSchool) {
  const seedValue = String(
    selectedSchool?.id ||
      selectedSchool?.db_name ||
      selectedSchool?.school_name ||
      'schooladmin'
  )
  const random = mulberry32(hashString(seedValue))
  const int = (min, max) => Math.floor(random() * (max - min + 1)) + min
  const float = (min, max, decimals = 1) =>
    Math.round((min + random() * (max - min)) * 10 ** decimals) / 10 ** decimals

  const buildTrend = (points, start, volatility, min, max) => {
    const values = [start]
    for (let i = 1; i < points; i += 1) {
      const prev = values[i - 1]
      const next = clamp(prev + (random() - 0.45) * volatility, min, max)
      values.push(Math.round(next * 10) / 10)
    }
    return values
  }

  const enrolledStudents = int(850, 2650)
  const employees = int(45, 175)
  const attendanceToday = float(88.5, 98.2, 1)
  const upcomingEvents = int(1, 12)

  const memosThisWeek = int(4, 22)
  const unreadMemos = int(0, Math.min(9, memosThisWeek))

  const collectionsToday = int(25000, 240000)
  const receivables = int(120000, 980000)
  const expensesMtd = int(90000, 640000)

  const pendingEnrollments = int(3, 32)
  const pendingTasks = int(0, 18)

  const kpis = [
    {
      key: 'calendar',
      title: 'Calendar',
      value: upcomingEvents,
      helper: 'Upcoming events (30 days)',
      icon: CalendarDays,
      href: '/admin/calendar',
      delta: float(-4.5, 12.5, 1),
      trend: buildTrend(14, upcomingEvents, 2.2, 0, 18),
      colorClass: 'text-primary',
    },
    {
      key: 'memos',
      title: 'Memo Board',
      value: memosThisWeek,
      helper: `${unreadMemos} unread this week`,
      icon: Megaphone,
      href: '/admin/memo-board',
      delta: float(-6.0, 16.0, 1),
      trend: buildTrend(14, memosThisWeek, 4.4, 0, 30),
      colorClass: 'text-primary',
    },
    {
      key: 'finance',
      title: 'Finance',
      value: collectionsToday,
      helper: 'Collections today',
      icon: DollarSign,
      href: '/admin/finance/cashier-transactions',
      delta: float(-2.4, 14.0, 1),
      trend: buildTrend(14, collectionsToday / 1000, 35, 5, 320),
      colorClass: 'text-primary',
      format: 'currency',
    },
    {
      key: 'hr',
      title: 'HR',
      value: employees,
      helper: 'Active employees',
      icon: UsersRound,
      href: '/admin/hr/employee-profile',
      delta: float(-1.8, 7.8, 1),
      trend: buildTrend(14, employees, 3.1, 30, 220),
      colorClass: 'text-primary',
    },
    {
      key: 'registrar',
      title: 'Registrar',
      value: enrolledStudents,
      helper: `${pendingEnrollments} pending enrollments`,
      icon: GraduationCap,
      href: '/admin/registrar/enrollment-summary',
      delta: float(-1.2, 9.5, 1),
      trend: buildTrend(14, enrolledStudents, 40, 500, 3200),
      colorClass: 'text-primary',
    },
    {
      key: 'attendance',
      title: 'Attendance',
      value: attendanceToday,
      helper: 'Today’s attendance rate',
      icon: UsersRound,
      href: '/admin/hr/employee-attendance',
      delta: float(-2.0, 2.8, 1),
      trend: buildTrend(14, attendanceToday, 1.8, 82, 99),
      colorClass: 'text-primary',
      format: 'percent',
    },
    {
      key: 'receivables',
      title: 'Receivables',
      value: receivables,
      helper: 'Outstanding balance',
      icon: DollarSign,
      href: '/admin/finance/account-receivables',
      delta: float(-8.5, 4.6, 1),
      trend: buildTrend(14, receivables / 1000, 45, 50, 1200),
      colorClass: 'text-primary',
      format: 'currency',
    },
    {
      key: 'tasks',
      title: 'Tasks',
      value: pendingTasks,
      helper: 'Pending actions',
      icon: TrendingUp,
      href: '/admin/dashboard',
      delta: float(-4.0, 10.0, 1),
      trend: buildTrend(14, pendingTasks, 1.5, 0, 24),
      colorClass: 'text-primary',
    },
  ]

  const months = Array.from({ length: 12 }).map((_, i) =>
    new Date(new Date().getFullYear(), new Date().getMonth() - (11 - i), 1).toLocaleString('en-US', {
      month: 'short',
    })
  )

  const collectionsMonthly = buildTrend(12, collectionsToday * 28, collectionsToday * 0.35, 120000, 4200000).map(
    (v) => Math.round(v)
  )
  const expensesMonthly = buildTrend(12, expensesMtd * 1.1, expensesMtd * 0.3, 90000, 3400000).map((v) =>
    Math.round(v)
  )

  const recentMemos = Array.from({ length: 4 }).map((_, i) => ({
    id: i + 1,
    title: [
      'Updated Faculty Guidelines',
      'Parent-Teacher Conference Schedule',
      'Monthly Safety Drill Memo',
      'Quarterly Performance Reminder',
      'New Attendance Policy',
    ][i] || `Memo #${i + 1}`,
    audience: int(0, 1) ? 'All Employees' : `${int(3, 24)} Employees`,
  }))

  const upcoming = Array.from({ length: 4 }).map((_, i) => {
    const dayOffset = int(1, 18)
    const date = new Date()
    date.setDate(date.getDate() + dayOffset)
    return {
      id: i + 1,
      title: ['PTA Meeting', 'Sports Fest', 'Faculty Workshop', 'Exam Week'][i] || `Event #${i + 1}`,
      when: date.toLocaleDateString(),
    }
  })

  return {
    kpis,
    financeSeries: [
      { label: 'Collections', data: collectionsMonthly, stroke: 'hsl(var(--primary))' },
      { label: 'Expenses', data: expensesMonthly, stroke: 'hsl(var(--muted-foreground))' },
    ],
    monthLabels: months,
    quickStats: {
      receivables,
      expensesMtd,
      pendingEnrollments,
      attendanceToday,
      pendingTasks,
    },
    lists: {
      memos: recentMemos,
      events: upcoming,
    },
  }
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(value)

const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value)

const formatPercent = (value) =>
  `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 }).format(value)}%`

function StatCard({ title, value, helper, icon: Icon, delta, trend, href, format }) {
  const isPositive = delta >= 0
  const DeltaIcon = isPositive ? TrendingUp : TrendingDown

  const formattedValue = (() => {
    if (format === 'currency') return formatCurrency(value)
    if (format === 'percent') return formatPercent(value)
    return formatNumber(value)
  })()

  return (
    <Card className='transition-colors hover:bg-muted/30'>
      <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
        <CardTitle className='text-sm font-medium'>{title}</CardTitle>
        {Icon ? <Icon className='h-4 w-4 text-muted-foreground' /> : null}
      </CardHeader>
      <CardContent className='space-y-3'>
        <div className='flex items-start justify-between gap-3'>
          <div className='min-w-0'>
            <div className='text-2xl font-bold truncate'>{formattedValue}</div>
            <p className='text-xs text-muted-foreground'>{helper}</p>
          </div>
          <div className='flex flex-col items-end gap-1'>
            <Badge variant={isPositive ? 'default' : 'secondary'} className='gap-1'>
              <DeltaIcon className='h-3.5 w-3.5' />
              {formatPercent(Math.abs(delta))}
            </Badge>
            <Sparkline data={trend} className='text-muted-foreground' />
          </div>
        </div>
        {href ? (
          <Button asChild variant='ghost' size='sm' className='px-0 justify-start'>
            <Link to={href}>View details</Link>
          </Button>
        ) : null}
      </CardContent>
    </Card>
  )
}

export default function AdminDashboard() {
  const selectedSchool = useMemo(
    () => JSON.parse(localStorage.getItem('selectedSchool') || 'null'),
    []
  )

  const data = useMemo(() => generateMockDashboard(selectedSchool), [selectedSchool])

  return (
    <div className='space-y-6'>
      <div className='flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between'>
        <div>
          <h2 className='text-2xl font-bold tracking-tight'>School Dashboard</h2>
          <p className='text-muted-foreground'>
            Overview for {selectedSchool?.school_name || 'your school'} (mock data)
          </p>
        </div>
        <div className='flex flex-wrap gap-2'>
          <Button asChild variant='outline' size='sm'>
            <Link to='/admin/memo-board'>Open Memo Board</Link>
          </Button>
          <Button asChild variant='outline' size='sm'>
            <Link to='/admin/hr/employee-profile'>Employee Profile</Link>
          </Button>
          <Button asChild variant='outline' size='sm'>
            <Link to='/admin/settings/profile'>Settings</Link>
          </Button>
        </div>
      </div>

      <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-4'>
        {data.kpis.map((item) => (
          <StatCard key={item.key} {...item} />
        ))}
      </div>

      <div className='grid gap-4 lg:grid-cols-7'>
        <Card className='lg:col-span-4'>
          <CardHeader>
            <CardTitle>Finance Overview</CardTitle>
            <CardDescription>Collections vs expenses (last 12 months)</CardDescription>
          </CardHeader>
          <CardContent className='pt-0'>
            <MiniLineChart series={data.financeSeries} />
            <div className='mt-4 grid gap-2 text-sm sm:grid-cols-3'>
              <div>
                <div className='text-muted-foreground'>Receivables</div>
                <div className='font-semibold'>{formatCurrency(data.quickStats.receivables)}</div>
              </div>
              <div>
                <div className='text-muted-foreground'>Expenses (MTD)</div>
                <div className='font-semibold'>{formatCurrency(data.quickStats.expensesMtd)}</div>
              </div>
              <div>
                <div className='text-muted-foreground'>Pending tasks</div>
                <div className='font-semibold'>{formatNumber(data.quickStats.pendingTasks)}</div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className='lg:col-span-3'>
          <CardHeader>
            <CardTitle>Activity</CardTitle>
            <CardDescription>What needs your attention</CardDescription>
          </CardHeader>
          <CardContent className='space-y-4'>
            <div className='space-y-2'>
              <div className='flex items-center justify-between'>
                <div className='font-medium'>Upcoming events</div>
                <Button asChild variant='ghost' size='sm' className='h-8 px-2'>
                  <Link to='/admin/calendar'>Calendar</Link>
                </Button>
              </div>
              <div className='space-y-2'>
                {data.lists.events.map((event) => (
                  <div key={event.id} className='flex items-start justify-between gap-3 text-sm'>
                    <div className='min-w-0'>
                      <div className='truncate font-medium'>{event.title}</div>
                      <div className='text-xs text-muted-foreground'>{event.when}</div>
                    </div>
                    <Badge variant='secondary' className='shrink-0'>
                      Event
                    </Badge>
                  </div>
                ))}
              </div>
            </div>

            <Separator />

            <div className='space-y-2'>
              <div className='flex items-center justify-between'>
                <div className='font-medium'>Recent memos</div>
                <Button asChild variant='ghost' size='sm' className='h-8 px-2'>
                  <Link to='/admin/memo-board'>View</Link>
                </Button>
              </div>
              <div className='space-y-2'>
                {data.lists.memos.map((memo) => (
                  <div key={memo.id} className='flex items-start justify-between gap-3 text-sm'>
                    <div className='min-w-0'>
                      <div className='truncate font-medium'>{memo.title}</div>
                      <div className='text-xs text-muted-foreground'>{memo.audience}</div>
                    </div>
                    <Badge variant='outline' className='shrink-0'>
                      Memo
                    </Badge>
                  </div>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
