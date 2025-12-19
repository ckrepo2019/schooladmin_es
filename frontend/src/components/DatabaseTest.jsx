import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export default function DatabaseTest() {
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    document.title = 'School Admin | Database Test'
  }, [])

  const testDatabase = async () => {
    setLoading(true)
    setError(null)
    setResult(null)

    try {
      const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:5000'
      const response = await fetch(`${apiUrl}/api/db-test`)
      const data = await response.json()

      if (response.ok) {
        setResult(data)
      } else {
        setError(data.message || 'Failed to connect to database')
      }
    } catch (err) {
      setError(err.message || 'Failed to connect to server')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-[calc(100vh-4rem)] flex items-center justify-center p-4">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>Database Connection Test</CardTitle>
          <CardDescription>
            Test the connection to the MySQL database
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Button
            onClick={testDatabase}
            disabled={loading}
            className="w-full"
          >
            {loading ? 'Testing...' : 'Test Database Connection'}
          </Button>

          {result && (
            <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
              <p className="font-semibold text-green-800">Success!</p>
              <p className="text-sm text-green-700">{result.message}</p>
              {result.result && (
                <pre className="mt-2 text-xs text-green-600">
                  {JSON.stringify(result.result, null, 2)}
                </pre>
              )}
            </div>
          )}

          {error && (
            <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
              <p className="font-semibold text-red-800">Error!</p>
              <p className="text-sm text-red-700">{error}</p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
