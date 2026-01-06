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
  Users,
  ClipboardList,
  Activity,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { Progress } from '@/components/ui/progress'

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

function BarChart({ data, height = 200 }) {
  const maxValue = Math.max(...data.map((d) => d.value))
  const barWidth = 100 / data.length

  return (
    <div className='flex h-full items-end justify-between gap-2' style={{ height: `${height}px` }}>
      {data.map((item, index) => {
        const barHeight = (item.value / maxValue) * 100
        return (
          <div key={index} className='flex flex-1 flex-col items-center gap-2'>
            <div className='flex w-full flex-1 items-end'>
              <div
                className='w-full rounded-t-md bg-primary transition-all hover:opacity-80'
                style={{ height: `${barHeight}%` }}
                title={`${item.label}: ${item.value}`}
              />
            </div>
            <div className='text-xs font-medium text-muted-foreground'>{item.label}</div>
          </div>
        )
      })}
    </div>
  )
}

function DonutChart({ data, size = 120 }) {
  const total = data.reduce((sum, item) => sum + item.value, 0)
  const radius = size / 2 - 10
  const circumference = 2 * Math.PI * radius
  let currentAngle = -90

  const colors = [
    'hsl(var(--primary))',
    'hsl(var(--chart-2))',
    'hsl(var(--chart-3))',
    'hsl(var(--chart-4))',
    'hsl(var(--chart-5))',
  ]

  return (
    <div className='flex items-center gap-4'>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill='none'
          stroke='hsl(var(--muted))'
          strokeWidth='16'
        />
        {data.map((item, index) => {
          const percentage = (item.value / total) * 100
          const dashOffset = circumference - (percentage / 100) * circumference
          const rotation = currentAngle
          currentAngle += (percentage / 100) * 360

          return (
            <circle
              key={index}
              cx={size / 2}
              cy={size / 2}
              r={radius}
              fill='none'
              stroke={colors[index % colors.length]}
              strokeWidth='16'
              strokeDasharray={circumference}
              strokeDashoffset={dashOffset}
              transform={`rotate(${rotation} ${size / 2} ${size / 2})`}
              className='transition-all'
            />
          )
        })}
      </svg>
      <div className='flex-1 space-y-2'>
        {data.map((item, index) => {
          const percentage = ((item.value / total) * 100).toFixed(1)
          return (
            <div key={index} className='flex items-center gap-2 text-sm'>
              <div
                className='h-3 w-3 rounded-sm'
                style={{ backgroundColor: colors[index % colors.length] }}
              />
              <span className='flex-1 text-muted-foreground'>{item.label}</span>
              <span className='font-medium'>{percentage}%</span>
            </div>
          )
        })}
      </div>
    </div>
  )
}

function ProgressRing({ value, max, label, size = 100 }) {
  const percentage = (value / max) * 100
  const radius = (size - 12) / 2
  const circumference = 2 * Math.PI * radius
  const offset = circumference - (percentage / 100) * circumference

  return (
    <div className='flex flex-col items-center gap-2'>
      <div className='relative'>
        <svg width={size} height={size} className='-rotate-90'>
          <circle
            cx={size / 2}
            cy={size / 2}
            r={radius}
            fill='none'
            stroke='hsl(var(--muted))'
            strokeWidth='8'
          />
          <circle
            cx={size / 2}
            cy={size / 2}
            r={radius}
            fill='none'
            stroke='hsl(var(--primary))'
            strokeWidth='8'
            strokeDasharray={circumference}
            strokeDashoffset={offset}
            strokeLinecap='round'
            className='transition-all duration-500'
          />
        </svg>
        <div className='absolute inset-0 flex items-center justify-center'>
          <div className='text-center'>
            <div className='text-lg font-bold'>{percentage.toFixed(0)}%</div>
          </div>
        </div>
      </div>
      <div className='text-center text-sm font-medium text-muted-foreground'>{label}</div>
    </div>
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
      watermark: 'CAL',
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
      watermark: 'MEMO',
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
      watermark: 'FIN',
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
      watermark: 'HR',
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
      watermark: 'REG',
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
      watermark: 'ATT',
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
      watermark: 'AR',
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
      watermark: 'TASK',
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

  const gradeLevels = [
    { label: 'Grade 7', value: int(120, 280) },
    { label: 'Grade 8', value: int(115, 270) },
    { label: 'Grade 9', value: int(110, 265) },
    { label: 'Grade 10', value: int(105, 250) },
    { label: 'Grade 11', value: int(95, 220) },
    { label: 'Grade 12', value: int(90, 210) },
  ]

  const departments = [
    { label: 'Academic', value: int(35, 85) },
    { label: 'Admin', value: int(12, 28) },
    { label: 'Support', value: int(8, 22) },
    { label: 'Maintenance', value: int(5, 15) },
  ]

  const studentGender = [
    { label: 'Male', value: int(400, 700) },
    { label: 'Female', value: int(400, 700) },
  ]

  const attendanceWeek = [
    { label: 'Mon', value: float(88, 98, 1) },
    { label: 'Tue', value: float(89, 98, 1) },
    { label: 'Wed', value: float(87, 97, 1) },
    { label: 'Thu', value: float(88, 98, 1) },
    { label: 'Fri', value: float(85, 96, 1) },
  ]

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
    charts: {
      gradeLevels,
      departments,
      studentGender,
      attendanceWeek,
    },
  }
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(value)

const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value)

const formatPercent = (value) =>
  `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 }).format(value)}%`

function StatCard({ title, value, helper, delta, trend, href, format, watermark }) {
  const isPositive = delta >= 0
  const DeltaIcon = isPositive ? TrendingUp : TrendingDown

  const formattedValue = (() => {
    if (format === 'currency') return formatCurrency(value)
    if (format === 'percent') return formatPercent(value)
    return formatNumber(value)
  })()

  return (
    <Card className='transition-colors hover:bg-muted/30' data-watermark={watermark}>
      <CardHeader className='pb-2'>
        <CardTitle className='text-sm font-medium'>{title}</CardTitle>
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
    <div className='space-y-6 p-4 md:p-6'>
      {/* Header Section */}
      <div className='flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between'>
        <div className='space-y-1'>
          <h2 className='text-3xl font-bold tracking-tight'>School Dashboard</h2>
          <p className='text-sm text-muted-foreground'>
            {selectedSchool?.school_name || 'Your school'} - Comprehensive analytics and insights
          </p>
        </div>
        <div className='flex flex-wrap gap-2'>
          <Button asChild variant='outline' size='sm'>
            <Link to='/admin/memo-board'>
              <Megaphone className='mr-2 h-4 w-4' />
              Memos
            </Link>
          </Button>
          <Button asChild variant='outline' size='sm'>
            <Link to='/admin/calendar'>
              <CalendarDays className='mr-2 h-4 w-4' />
              Calendar
            </Link>
          </Button>
        </div>
      </div>

      {/* Top KPI Cards - Full Width Grid */}
      <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
        {data.kpis.slice(0, 4).map((item) => (
          <StatCard key={item.key} {...item} />
        ))}
      </div>

      {/* Masonry Layout - Main Content */}
      <div className='grid gap-4 lg:grid-cols-3'>
        {/* Column 1 - Left */}
        <div className='space-y-4'>
          {/* Finance Overview */}
          <Card className='shadow-sm' data-watermark='FIN'>
            <CardHeader>
              <div className='flex items-center gap-2'>
                <DollarSign className='h-5 w-5 text-primary' />
                <CardTitle>Finance Overview</CardTitle>
              </div>
              <CardDescription>Collections vs expenses (12 months)</CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <MiniLineChart series={data.financeSeries} />
              <div className='grid gap-3 text-sm'>
                <div className='flex items-center justify-between rounded-lg border bg-muted/30 p-3'>
                  <div className='text-muted-foreground'>Receivables</div>
                  <div className='font-bold'>{formatCurrency(data.quickStats.receivables)}</div>
                </div>
                <div className='flex items-center justify-between rounded-lg border bg-muted/30 p-3'>
                  <div className='text-muted-foreground'>Expenses (MTD)</div>
                  <div className='font-bold'>{formatCurrency(data.quickStats.expensesMtd)}</div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Student Demographics */}
          <Card className='shadow-sm' data-watermark='DEMO'>
            <CardHeader>
              <div className='flex items-center gap-2'>
                <Users className='h-5 w-5 text-primary' />
                <CardTitle>Student Demographics</CardTitle>
              </div>
              <CardDescription>Gender distribution</CardDescription>
            </CardHeader>
            <CardContent>
              <DonutChart data={data.charts.studentGender} size={140} />
            </CardContent>
          </Card>

          {/* Attendance Trend */}
          <Card className='shadow-sm' data-watermark='ATT'>
            <CardHeader>
              <div className='flex items-center gap-2'>
                <Activity className='h-5 w-5 text-primary' />
                <CardTitle>Weekly Attendance</CardTitle>
              </div>
              <CardDescription>This week's attendance rates</CardDescription>
            </CardHeader>
            <CardContent>
              <BarChart data={data.charts.attendanceWeek} height={180} />
            </CardContent>
          </Card>
        </div>

        {/* Column 2 - Middle */}
        <div className='space-y-4'>
          {/* Enrollment by Grade */}
          <Card className='shadow-sm' data-watermark='ENR'>
            <CardHeader>
              <div className='flex items-center gap-2'>
                <GraduationCap className='h-5 w-5 text-primary' />
                <CardTitle>Enrollment by Grade</CardTitle>
              </div>
              <CardDescription>Student distribution across grade levels</CardDescription>
            </CardHeader>
            <CardContent>
              <BarChart data={data.charts.gradeLevels} height={220} />
            </CardContent>
          </Card>

          {/* Quick Stats */}
          <Card className='shadow-sm' data-watermark='STAT'>
            <CardHeader>
              <div className='flex items-center gap-2'>
                <ClipboardList className='h-5 w-5 text-primary' />
                <CardTitle>Quick Stats</CardTitle>
              </div>
              <CardDescription>Key metrics at a glance</CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <div className='grid grid-cols-2 gap-4'>
                <ProgressRing
                  value={data.quickStats.attendanceToday}
                  max={100}
                  label='Attendance'
                  size={90}
                />
                <ProgressRing
                  value={data.quickStats.pendingEnrollments}
                  max={50}
                  label='Pending'
                  size={90}
                />
              </div>
              <div className='space-y-2'>
                <div className='flex items-center justify-between text-sm'>
                  <span className='text-muted-foreground'>Pending Tasks</span>
                  <span className='font-semibold'>{formatNumber(data.quickStats.pendingTasks)}</span>
                </div>
                <Progress value={(data.quickStats.pendingTasks / 20) * 100} className='h-2' />
              </div>
            </CardContent>
          </Card>

          {/* More KPIs */}
          {data.kpis.slice(4, 6).map((item) => (
            <StatCard key={item.key} {...item} />
          ))}
        </div>

        {/* Column 3 - Right */}
        <div className='space-y-4'>
          {/* Department Breakdown */}
          <Card className='shadow-sm' data-watermark='DEPT'>
            <CardHeader>
              <div className='flex items-center gap-2'>
                <UsersRound className='h-5 w-5 text-primary' />
                <CardTitle>Department Breakdown</CardTitle>
              </div>
              <CardDescription>Employee distribution</CardDescription>
            </CardHeader>
            <CardContent>
              <DonutChart data={data.charts.departments} size={140} />
            </CardContent>
          </Card>

          {/* Activity Feed */}
          <Card className='shadow-sm' data-watermark='EVENT'>
            <CardHeader>
              <div className='flex items-center justify-between'>
                <div>
                  <CardTitle>Upcoming Events</CardTitle>
                  <CardDescription>Next 30 days</CardDescription>
                </div>
                <Button asChild variant='ghost' size='sm'>
                  <Link to='/admin/calendar'>View all</Link>
                </Button>
              </div>
            </CardHeader>
            <CardContent className='space-y-3'>
              {data.lists.events.map((event) => (
                <div
                  key={event.id}
                  className='flex items-start gap-3 rounded-lg border bg-muted/20 p-3 transition-colors hover:bg-muted/40'
                >
                  <CalendarDays className='mt-0.5 h-4 w-4 text-primary' />
                  <div className='flex-1 space-y-1'>
                    <div className='text-sm font-medium'>{event.title}</div>
                    <div className='text-xs text-muted-foreground'>{event.when}</div>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          {/* Recent Memos */}
          <Card className='shadow-sm' data-watermark='MEMO'>
            <CardHeader>
              <div className='flex items-center justify-between'>
                <div>
                  <CardTitle>Recent Memos</CardTitle>
                  <CardDescription>Latest communications</CardDescription>
                </div>
                <Button asChild variant='ghost' size='sm'>
                  <Link to='/admin/memo-board'>View all</Link>
                </Button>
              </div>
            </CardHeader>
            <CardContent className='space-y-3'>
              {data.lists.memos.map((memo) => (
                <div
                  key={memo.id}
                  className='flex items-start gap-3 rounded-lg border bg-muted/20 p-3 transition-colors hover:bg-muted/40'
                >
                  <Megaphone className='mt-0.5 h-4 w-4 text-primary' />
                  <div className='flex-1 space-y-1'>
                    <div className='text-sm font-medium'>{memo.title}</div>
                    <div className='text-xs text-muted-foreground'>{memo.audience}</div>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          {/* Additional KPIs */}
          {data.kpis.slice(6, 8).map((item) => (
            <StatCard key={item.key} {...item} />
          ))}
        </div>
      </div>
    </div>
  )
}
