import type { Metadata } from 'next';
import './globals.css';
export const metadata:Metadata={title:'Démos Kratos — La mémoire de nos chemins',description:'Populorum : explorer, expérimenter et transmettre. Simulateur fictif de démocratie participative fondé sur la convergence émergente.',icons:{icon:'/favicon.svg'}};
export default function RootLayout({children}:{children:React.ReactNode}){return <html lang="fr"><body>{children}</body></html>}
