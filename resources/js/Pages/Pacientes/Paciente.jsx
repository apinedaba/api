import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ResumePaciente from './Partials/Resume';
import AppointmentsTab from './Partials/AppointmentsTab';
import { Head } from '@inertiajs/react';

export default function Edit({ auth, paciente }) {
    const [activeTab, setActiveTab] = useState('info');

    const tabs = [
        { id: 'info', label: 'Información del Paciente' },
        { id: 'appointments', label: 'Citas' }
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                    Paciente: {paciente.name}
                </h2>
            }
        >
            <Head title={`Paciente - ${paciente.name}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {/* Tabs */}
                    <div className="bg-white shadow sm:rounded-lg">
                        <div className="border-b border-gray-200">
                            <nav className="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                                {tabs.map((tab) => (
                                    <button
                                        key={tab.id}
                                        onClick={() => setActiveTab(tab.id)}
                                        className={`
                                            ${activeTab === tab.id
                                                ? 'border-indigo-500 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                            }
                                            whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm
                                        `}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </nav>
                        </div>

                        <div className="p-6">
                            {activeTab === 'info' && (
                                <ResumePaciente paciente={paciente} />
                            )}
                            {activeTab === 'appointments' && (
                                <AppointmentsTab patientId={paciente.id} />
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
