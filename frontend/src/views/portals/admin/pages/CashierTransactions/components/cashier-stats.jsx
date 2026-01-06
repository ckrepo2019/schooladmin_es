import { Card, CardContent, CardHeader, CardTitle } from '../../../../../../components/ui/card';
import { Progress } from '../../../../../../components/ui/progress';

const toNumber = (value) => Number(value) || 0;

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(toNumber(value));
};

const formatNumber = (value) => new Intl.NumberFormat('en-US').format(toNumber(value));

export function CashierStats({ data }) {
  if (!data) {
    return null;
  }

  const { summary, byPaymentType, byClassification, byTerminal, dailyCollections } = data;

  const totalCollections = toNumber(summary?.total_collections);
  const cancelledAmount = toNumber(summary?.cancelled_amount);
  const totalTransactions = toNumber(summary?.total_transactions);
  const cancelledCount = toNumber(summary?.cancelled_count);
  const overpaymentAmount = toNumber(summary?.overpayment_amount);
  const overpaymentCount = toNumber(summary?.overpayment_count);
  const activeCollections = totalCollections - cancelledAmount;
  const activeTransactions = totalTransactions - cancelledCount;
  const averagePerTransaction = activeTransactions > 0 ? activeCollections / activeTransactions : 0;
  const averageOverpayment = overpaymentCount > 0 ? overpaymentAmount / overpaymentCount : 0;
  const cancelledRate = totalTransactions > 0 ? (cancelledCount / totalTransactions) * 100 : 0;
  const activeRate = totalTransactions > 0 ? (activeTransactions / totalTransactions) * 100 : 0;
  const overpaymentRate = totalTransactions > 0 ? (overpaymentCount / totalTransactions) * 100 : 0;

  const paymentTypeTotal = (byPaymentType || []).reduce(
    (sum, item) => sum + toNumber(item.total_amount),
    0
  );
  const classificationTotal = (byClassification || []).reduce(
    (sum, item) => sum + toNumber(item.total_amount),
    0
  );
  const terminalTotal = (byTerminal || []).reduce(
    (sum, item) => sum + toNumber(item.total_amount),
    0
  );
  const dailyMaxAmount = Math.max(
    0,
    ...((dailyCollections || []).map((day) => toNumber(day.total_amount)))
  );

  const topPaymentTypes = (byPaymentType || []).slice(0, 4);
  const topClassifications = (byClassification || []).slice(0, 5);
  const topTerminals = (byTerminal || []).slice(0, 5);

  return (
    <div className="space-y-6">
      {/* Summary Overview */}
      <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div className="space-y-4">
          <Card data-watermark="PESO">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Collections Overview</CardTitle>
            <p className="text-xs text-muted-foreground">Active vs cancelled totals</p>
          </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-xl border bg-gradient-to-br from-emerald-500/10 via-transparent to-emerald-500/5 p-4">
                <div className="text-xs text-muted-foreground">Active collections</div>
                <div className="text-3xl font-semibold tracking-tight">
                  {formatCurrency(activeCollections)}
                </div>
                <div className="mt-2 text-xs text-muted-foreground">
                  {formatNumber(activeTransactions)} active transactions
                </div>
              </div>
              <div className="grid gap-3 sm:grid-cols-3">
                <div className="rounded-lg border bg-background/60 px-3 py-2">
                  <div className="text-xs text-muted-foreground">Total collections</div>
                  <div className="text-sm font-semibold">{formatCurrency(totalCollections)}</div>
                </div>
                <div className="rounded-lg border bg-background/60 px-3 py-2">
                  <div className="text-xs text-muted-foreground">Cancelled amount</div>
                  <div className="text-sm font-semibold text-destructive">
                    {formatCurrency(cancelledAmount)}
                  </div>
                </div>
                <div className="rounded-lg border bg-background/60 px-3 py-2">
                  <div className="text-xs text-muted-foreground">Average value</div>
                  <div className="text-sm font-semibold">{formatCurrency(averagePerTransaction)}</div>
                </div>
              </div>
              <div className="space-y-2">
                <div className="flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">Active transaction rate</span>
                  <span className="font-medium">{activeRate.toFixed(1)}%</span>
                </div>
                <Progress value={activeRate} className="h-2" />
              </div>
            </CardContent>
          </Card>

          <div className="grid gap-4 md:grid-cols-2">
            <Card data-watermark="TYPE">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">By Payment Type</CardTitle>
              <p className="text-xs text-muted-foreground mt-1">Collection breakdown</p>
            </CardHeader>
              <CardContent className="space-y-3">
                {topPaymentTypes.length > 0 ? (
                  topPaymentTypes.map((item, idx) => {
                    const percentage =
                      paymentTypeTotal > 0
                        ? (toNumber(item.total_amount) / paymentTypeTotal) * 100
                        : 0;
                    return (
                      <div key={idx} className="space-y-1">
                        <div className="flex items-center justify-between text-xs">
                          <span className="text-muted-foreground">
                            {item.payment_type || 'Unknown'}
                          </span>
                          <span className="font-medium">
                            {formatCurrency(item.total_amount)} ({formatNumber(item.transaction_count)})
                          </span>
                        </div>
                        <Progress value={percentage} className="h-2" />
                      </div>
                    );
                  })
                ) : (
                  <p className="text-xs text-muted-foreground">No payment data</p>
                )}
              </CardContent>
            </Card>

            <Card data-watermark="CLASS">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">By Item Classification</CardTitle>
              <p className="text-xs text-muted-foreground mt-1">Top fee categories</p>
            </CardHeader>
              <CardContent className="space-y-3">
                {topClassifications.length > 0 ? (
                  topClassifications.map((item, idx) => {
                    const percentage =
                      classificationTotal > 0
                        ? (toNumber(item.total_amount) / classificationTotal) * 100
                        : 0;
                    return (
                      <div key={idx} className="space-y-1">
                        <div className="flex items-center justify-between text-xs">
                          <span className="text-muted-foreground truncate max-w-[150px]">
                            {item.classification || 'Unknown'}
                          </span>
                          <span className="font-medium">{formatCurrency(item.total_amount)}</span>
                        </div>
                        <Progress value={percentage} className="h-2" />
                      </div>
                    );
                  })
                ) : (
                  <p className="text-xs text-muted-foreground">No classification data</p>
                )}
              </CardContent>
            </Card>
          </div>
        </div>

        <div className="space-y-4">
          <Card data-watermark="TXN">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Transaction Health</CardTitle>
            <p className="text-xs text-muted-foreground">Volume and quality</p>
          </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-1">
                <div className="text-xs text-muted-foreground">Total transactions</div>
                <div className="text-2xl font-semibold">{formatNumber(totalTransactions)}</div>
              </div>
              <div className="rounded-lg border bg-muted/40 p-3">
                <div className="flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">Cancelled rate</span>
                  <span className="font-medium text-destructive">{cancelledRate.toFixed(1)}%</span>
                </div>
                <div className="mt-2">
                  <Progress value={cancelledRate} className="h-2" indicatorClassName="bg-red-500" />
                </div>
                <div className="mt-2 text-xs text-muted-foreground">
                  {formatNumber(cancelledCount)} cancelled transactions
                </div>
              </div>
              <div className="grid gap-2 text-xs">
                <div className="flex items-center justify-between rounded-md border bg-background/60 px-3 py-2">
                  <span className="text-muted-foreground">Active transactions</span>
                  <span className="font-medium">{formatNumber(activeTransactions)}</span>
                </div>
                <div className="flex items-center justify-between rounded-md border bg-background/60 px-3 py-2">
                  <span className="text-muted-foreground">Average per transaction</span>
                  <span className="font-medium">{formatCurrency(averagePerTransaction)}</span>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card data-watermark="OVER">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Overpayments</CardTitle>
            <p className="text-xs text-muted-foreground">Excess payment tracking</p>
          </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-1">
                <div className="text-xs text-muted-foreground">Total overpayment</div>
                <div className="text-2xl font-semibold">{formatCurrency(overpaymentAmount)}</div>
                <div className="text-xs text-muted-foreground">
                  {formatNumber(overpaymentCount)} transactions
                </div>
              </div>
              <div className="rounded-lg border bg-muted/40 p-3">
                <div className="flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">Overpayment rate</span>
                  <span className="font-medium">{overpaymentRate.toFixed(1)}%</span>
                </div>
                <div className="mt-2">
                  <Progress value={overpaymentRate} className="h-2" indicatorClassName="bg-amber-500" />
                </div>
              </div>
              <div className="flex items-center justify-between rounded-md border bg-background/60 px-3 py-2 text-xs">
                <span className="text-muted-foreground">Average overpayment</span>
                <span className="font-medium">{formatCurrency(averageOverpayment)}</span>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <Card data-watermark="CASH">
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium">By Terminal/Cashier</CardTitle>
          <p className="text-xs text-muted-foreground mt-1">Collections per cashier</p>
        </CardHeader>
        <CardContent className="space-y-3">
          {topTerminals.length > 0 ? (
            topTerminals.map((item, idx) => {
              const percentage =
                terminalTotal > 0 ? (toNumber(item.total_amount) / terminalTotal) * 100 : 0;
              return (
                <div key={idx} className="space-y-1">
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-muted-foreground">
                      {item.terminal || 'Unknown'} ({item.cashier})
                    </span>
                    <span className="font-medium">{formatCurrency(item.total_amount)}</span>
                  </div>
                  <Progress value={percentage} className="h-2" />
                </div>
              );
            })
          ) : (
            <p className="text-xs text-muted-foreground">No terminal data</p>
          )}
        </CardContent>
      </Card>

      {/* Daily Collections Trend */}
      <Card data-watermark="DAILY">
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium">Daily Collections (Last 7 Days)</CardTitle>
          <p className="text-xs text-muted-foreground mt-1">Recent collection trend</p>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {dailyCollections && dailyCollections.length > 0 ? (
              dailyCollections.map((day, idx) => {
                const percentage =
                  dailyMaxAmount > 0 ? (toNumber(day.total_amount) / dailyMaxAmount) * 100 : 0;
                return (
                  <div key={idx} className="space-y-2">
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-muted-foreground">
                        {new Date(day.date).toLocaleDateString('en-US', {
                          month: 'short',
                          day: 'numeric',
                          year: 'numeric',
                        })}
                      </span>
                      <span className="font-medium">
                        {formatCurrency(day.total_amount)} ({formatNumber(day.transaction_count)} trans)
                      </span>
                    </div>
                    <div className="relative h-2 overflow-hidden rounded-full bg-muted">
                      <div
                        className="h-full rounded-full bg-emerald-500 transition-all"
                        style={{ width: `${percentage}%` }}
                      />
                    </div>
                  </div>
                );
              })
            ) : (
              <p className="text-sm text-muted-foreground">No daily data available</p>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
