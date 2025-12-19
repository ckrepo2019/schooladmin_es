import { createContext, useContext, useState } from 'react'

const EmployeesContext = createContext(null)

export function EmployeesProvider({ children }) {
  const [open, setOpen] = useState(null)
  const [currentRow, setCurrentRow] = useState(null)

  const value = {
    open,
    setOpen,
    currentRow,
    setCurrentRow,
  }

  return (
    <EmployeesContext.Provider value={value}>
      {children}
    </EmployeesContext.Provider>
  )
}

export const useEmployees = () => {
  const context = useContext(EmployeesContext)
  if (!context) {
    throw new Error('useEmployees must be used within EmployeesProvider')
  }
  return context
}
