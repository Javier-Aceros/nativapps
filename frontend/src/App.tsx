import './App.css'
import { SenderForm } from './features/sender/components/SenderForm'

function App() {
  return (
    <div className="app-layout">
      <header className="app-header">
        <h1 className="app-header__logo">MCCP</h1>
        <p className="app-header__subtitle">Multi-Channel Content Processor</p>
      </header>
      <main className="app-main">
        <SenderForm />
      </main>
    </div>
  )
}

export default App
