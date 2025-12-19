import { createContext, useContext, useState } from 'react'

const SchoolsContext = createContext(undefined)

export function SchoolsProvider({ children }) {
  const [open, setOpen] = useState(null)
  const [currentRow, setCurrentRow] = useState(null)

  return (
    <SchoolsContext.Provider
      value={{
        open,
        setOpen,
        currentRow,
        setCurrentRow,
      }}
    >
      {children}
    </SchoolsContext.Provider>
  )
}

export const useSchools = () => {
  const context = useContext(SchoolsContext)

  if (!context) {
    throw new Error('useSchools must be used within SchoolsProvider')
  }

  return context
}
