import { Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';

export default function VendedorLayout({ vendedor, children }) {
    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="bg-white border-b border-gray-200 shadow-sm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16 items-center">
                        <div className="flex items-center gap-4">
                            <Link href={route('vendedor.dashboard')}>
                                <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                            </Link>
                            <span className="hidden sm:block text-sm font-semibold text-indigo-700 bg-indigo-50 px-3 py-1 rounded-full">
                                Panel Vendedor
                            </span>
                        </div>

                        <div className="flex items-center gap-4">
                            <span className="text-sm text-gray-600">
                                {vendedor?.nombre}
                            </span>
                            <Link
                                href={route('vendedor.logout')}
                                method="post"
                                as="button"
                                className="text-sm text-gray-500 hover:text-red-600 transition-colors duration-150"
                            >
                                Cerrar sesión
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {children}
            </main>
        </div>
    );
}
