import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { apiUrl } from '../../../../../lib/api';
import { Card, CardContent } from '../../../../../components/ui/card';
import { Button } from '../../../../../components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../../../components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../../../../../components/ui/tabs';
import { RefreshCcw, Download } from 'lucide-react';
import { ReceivablesStats } from './components/receivables-stats';
import { ReceivablesTable } from './components/receivables-table';

const PER_PAGE = 200;

export default function AccountReceivables() {
  const [summaryData, setSummaryData] = useState(null);
  const [receivables, setReceivables] = useState([]);
  const [programs, setPrograms] = useState([]);
  const [gradeLevels, setGradeLevels] = useState([]);
  const [schoolYears, setSchoolYears] = useState([]);
  const [semesters, setSemesters] = useState([]);
  const [summaryLoading, setSummaryLoading] = useState(false);
  const [listLoading, setListLoading] = useState(false);

  const [selectedSy, setSelectedSy] = useState('');
  const [selectedSem, setSelectedSem] = useState('');
  const [selectedProgram, setSelectedProgram] = useState('all');
  const [selectedLevel, setSelectedLevel] = useState('all');

  const summaryAbortRef = useRef(null);
  const listAbortRef = useRef(null);
  const debounceRef = useRef(null);

  const selectedSchool = JSON.parse(localStorage.getItem('selectedSchool') || 'null');
  const token = localStorage.getItem('token');

  const schoolDbConfig = selectedSchool
    ? {
        db_host: selectedSchool.db_host || 'localhost',
        db_port: selectedSchool.db_port || 3306,
        db_name: selectedSchool.db_name,
        db_username: selectedSchool.db_username || 'root',
        db_password: selectedSchool.db_password || '',
      }
    : null;

  useEffect(() => {
    if (!selectedSchool) {
      toast.error('No school selected');
      return;
    }
    fetchSchoolYears();
    fetchSemesters();
    fetchFilters();
  }, []);

  useEffect(() => {
    if (!selectedSy || !selectedSem) return;

    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    debounceRef.current = setTimeout(() => {
      fetchReceivablesSummary();
      fetchReceivablesList();
    }, 350);

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, [selectedSy, selectedSem, selectedProgram, selectedLevel]);

  useEffect(() => {
    return () => {
      if (summaryAbortRef.current) {
        summaryAbortRef.current.abort();
      }
      if (listAbortRef.current) {
        listAbortRef.current.abort();
      }
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, []);

  useEffect(() => {
    if (selectedProgram === 'all') return;
    const programId = Number(selectedProgram);
    const validLevels = gradeLevels.filter((level) => Number(level.acadprogid) === programId);
    if (selectedLevel !== 'all' && !validLevels.some((level) => `${level.id}` === selectedLevel)) {
      setSelectedLevel('all');
    }
  }, [selectedProgram, gradeLevels, selectedLevel]);

  const fetchSchoolYears = async () => {
    try {
      const response = await fetch(apiUrl('/api/admin/enrollment/school-years'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        credentials: 'include',
        body: JSON.stringify({ schoolDbConfig }),
      });

      const result = await response.json();
      if (response.ok && result.status === 'success') {
        setSchoolYears(result.data || []);
        const active = result.data.find((sy) => sy.isactive === 1);
        if (active) {
          setSelectedSy(active.id.toString());
        } else if (result.data.length > 0) {
          setSelectedSy(result.data[0].id.toString());
        }
      }
    } catch (error) {
      console.error('Error fetching school years:', error);
    }
  };

  const fetchSemesters = async () => {
    try {
      const response = await fetch(apiUrl('/api/admin/enrollment/semesters'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        credentials: 'include',
        body: JSON.stringify({ schoolDbConfig }),
      });

      const result = await response.json();
      if (response.ok && result.status === 'success') {
        setSemesters(result.data || []);
        const active = result.data.find((sem) => sem.isactive === 1);
        if (active) {
          setSelectedSem(active.id.toString());
        } else if (result.data.length > 0) {
          setSelectedSem(result.data[0].id.toString());
        }
      }
    } catch (error) {
      console.error('Error fetching semesters:', error);
    }
  };

  const fetchFilters = async () => {
    try {
      const response = await fetch(apiUrl('/api/admin/receivables/filters'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        credentials: 'include',
        body: JSON.stringify({ schoolDbConfig }),
      });

      const result = await response.json();
      if (response.ok && result.status === 'success') {
        setPrograms(result.data.programs || []);
        setGradeLevels(result.data.gradeLevels || []);
      }
    } catch (error) {
      console.error('Error fetching receivable filters:', error);
    }
  };

  const fetchReceivablesSummary = async () => {
    if (summaryAbortRef.current) {
      summaryAbortRef.current.abort();
    }
    const controller = new AbortController();
    summaryAbortRef.current = controller;

    try {
      setSummaryLoading(true);
      const response = await fetch(apiUrl('/api/admin/receivables/summary'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        credentials: 'include',
        signal: controller.signal,
        body: JSON.stringify({
          schoolDbConfig,
          syid: selectedSy,
          semid: selectedSem,
          programId: selectedProgram === 'all' ? null : selectedProgram,
          levelId: selectedLevel === 'all' ? null : selectedLevel,
        }),
      });

      const result = await response.json();
      if (response.ok && result.status === 'success') {
        setSummaryData(result.data);
      } else {
        toast.error(result.message || 'Failed to fetch receivables summary');
      }
    } catch (error) {
      if (error.name !== 'AbortError') {
        console.error('Error fetching receivables summary:', error);
        toast.error('An error occurred while fetching summary');
      }
    } finally {
      if (summaryAbortRef.current === controller) {
        setSummaryLoading(false);
      }
    }
  };

  const fetchReceivablesList = async () => {
    if (listAbortRef.current) {
      listAbortRef.current.abort();
    }
    const controller = new AbortController();
    listAbortRef.current = controller;

    try {
      setListLoading(true);
      const response = await fetch(apiUrl('/api/admin/receivables/list'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        credentials: 'include',
        signal: controller.signal,
        body: JSON.stringify({
          schoolDbConfig,
          syid: selectedSy,
          semid: selectedSem,
          programId: selectedProgram === 'all' ? null : selectedProgram,
          levelId: selectedLevel === 'all' ? null : selectedLevel,
          page: 1,
          perPage: PER_PAGE,
        }),
      });

      const result = await response.json();
      if (response.ok && result.status === 'success') {
        setReceivables(result.data || []);
      } else {
        toast.error(result.message || 'Failed to fetch receivables list');
      }
    } catch (error) {
      if (error.name !== 'AbortError') {
        console.error('Error fetching receivables list:', error);
        toast.error('An error occurred while fetching receivables list');
      }
    } finally {
      if (listAbortRef.current === controller) {
        setListLoading(false);
      }
    }
  };

  const handleExport = () => {
    if (!receivables || receivables.length === 0) {
      toast.error('No data to export');
      return;
    }

    const headers = Object.keys(receivables[0]).join(',');
    const rows = receivables.map((row) =>
      Object.values(row).map((val) => `"${val ?? ''}"`).join(',')
    );
    const csvContent = [headers, ...rows].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', `account_receivables_${Date.now()}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    toast.success('Data exported successfully');
  };

  const handleRefresh = () => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }
    fetchReceivablesSummary();
    fetchReceivablesList();
  };

  const sortGradeLevels = (levels) => {
    return [...levels].sort((a, b) => {
      const aProg = Number(a.acadprogid);
      const bProg = Number(b.acadprogid);
      const aKey = Number.isFinite(aProg) ? (aProg === 1 ? 0 : aProg) : 999;
      const bKey = Number.isFinite(bProg) ? (bProg === 1 ? 0 : bProg) : 999;

      if (aKey !== bKey) {
        return aKey - bKey;
      }

      const aName = a.levelname || '';
      const bName = b.levelname || '';
      return aName.localeCompare(bName, undefined, { numeric: true, sensitivity: 'base' });
    });
  };

  const filteredLevels = sortGradeLevels(
    selectedProgram === 'all'
      ? gradeLevels
      : gradeLevels.filter((level) => `${level.acadprogid}` === selectedProgram)
  );

  return (
    <div className="space-y-4 p-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Account Receivables</h1>
          <p className="text-muted-foreground">Monitor outstanding balances across programs</p>
        </div>
        <div className="flex items-center gap-3">
          {summaryLoading && (
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
              <RefreshCcw className="h-3.5 w-3.5 animate-spin" />
              Updating...
            </div>
          )}
          <Button variant="outline" size="sm" onClick={handleRefresh}>
            <RefreshCcw className="h-4 w-4 mr-2" />
            Refresh
          </Button>
        </div>
      </div>

      <Card data-watermark="FILTER">
        <CardContent className="pt-6">
          <div className="space-y-4">
            <div className="flex flex-wrap items-end gap-3">
              <div className="w-[200px]">
                <label className="text-xs font-medium mb-1.5 block text-muted-foreground">
                  School Year
                </label>
                <Select value={selectedSy} onValueChange={setSelectedSy}>
                  <SelectTrigger className="w-full h-9">
                    <SelectValue placeholder="Select school year" />
                  </SelectTrigger>
                  <SelectContent>
                    {schoolYears.length === 0 && (
                      <SelectItem value="none" disabled>
                        No school years
                      </SelectItem>
                    )}
                    {schoolYears.map((sy) => (
                      <SelectItem key={sy.id} value={sy.id.toString()}>
                        {sy.sydesc}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="w-[200px]">
                <label className="text-xs font-medium mb-1.5 block text-muted-foreground">
                  Semester
                </label>
                <Select value={selectedSem} onValueChange={setSelectedSem}>
                  <SelectTrigger className="w-full h-9">
                    <SelectValue placeholder="Select semester" />
                  </SelectTrigger>
                  <SelectContent>
                    {semesters.length === 0 && (
                      <SelectItem value="none" disabled>
                        No semesters
                      </SelectItem>
                    )}
                    {semesters.map((sem) => (
                      <SelectItem key={sem.id} value={sem.id.toString()}>
                        {sem.semester}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="w-[220px]">
                <label className="text-xs font-medium mb-1.5 block text-muted-foreground">
                  Academic Program
                </label>
                <Select value={selectedProgram} onValueChange={setSelectedProgram}>
                  <SelectTrigger className="w-full h-9">
                    <SelectValue placeholder="All programs" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All programs</SelectItem>
                    {programs.map((program) => (
                      <SelectItem key={program.id} value={program.id.toString()}>
                        {program.progname}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="w-[220px]">
                <label className="text-xs font-medium mb-1.5 block text-muted-foreground">
                  Grade Level
                </label>
                <Select value={selectedLevel} onValueChange={setSelectedLevel}>
                  <SelectTrigger className="w-full h-9">
                    <SelectValue placeholder="All levels" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All levels</SelectItem>
                    {filteredLevels.map((level) => (
                      <SelectItem key={level.id} value={level.id.toString()}>
                        {level.levelname}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <Button variant="outline" size="sm" onClick={handleExport} className="h-9">
                <Download className="h-4 w-4 mr-2" />
                Export
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Tabs defaultValue="summary" className="space-y-4">
        <TabsList>
          <TabsTrigger value="summary">Summary & Analysis</TabsTrigger>
          <TabsTrigger value="receivables">Receivables</TabsTrigger>
        </TabsList>

        <TabsContent value="summary" className="space-y-4">
          {summaryLoading && !summaryData ? (
            <div className="flex items-center justify-center min-h-[240px]">
              <div className="text-center">
                <RefreshCcw className="h-8 w-8 animate-spin mx-auto mb-2 text-primary" />
                <p className="text-sm text-muted-foreground">Loading summary data...</p>
              </div>
            </div>
          ) : (
            <ReceivablesStats data={summaryData} />
          )}
        </TabsContent>

        <TabsContent value="receivables" className="space-y-4">
          <Card data-watermark="TABLE">
            <CardContent className="pt-6">
              <ReceivablesTable data={receivables} loading={listLoading} />
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
