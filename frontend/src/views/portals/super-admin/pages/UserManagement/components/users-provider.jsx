import { createContext, useContext, useState } from 'react'

const UsersContext = createContext(undefined)

export function UsersProvider({ children }) {
  const [open, setOpen] = useState(null)
  const [currentRow, setCurrentRow] = useState(null)

  return (
    <UsersContext.Provider
      value={{
        open,
        setOpen,
        currentRow,
        setCurrentRow,
      }}
    >
      {children}
    </UsersContext.Provider>
  )
}

export const useUsers = () => {
  const context = useContext(UsersContext)

  if (!context) {
    throw new Error('useUsers must be used within UsersProvider')
  }

  return context
}
