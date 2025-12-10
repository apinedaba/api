import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import ResumePsicologo from './Partials/ResumePsicologo';
import ActiveUserForm from './Partials/ActiveUserForm';
import { Head } from '@inertiajs/react';
import ValidatePsicologo from './Partials/ValidatePsicologo';
import EducacionUser from './Partials/EducacionUser';

export default function Edit({ auth, psicologo }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Profile</h2>}
        >
            <Head title="Profile" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <ResumePsicologo
                            psicologo={psicologo}
                        />
                    </div>
                    <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <ValidatePsicologo
                            psicologo={psicologo}
                        />
                    </div>
                    {
                        psicologo?.activo &&
                        <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                            <DeleteUserForm className="max-w-xl" psicologo={psicologo} />
                        </div>
                    }
                    {
                        !psicologo?.activo &&
                        <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                            <ActiveUserForm className="max-w-xl" psicologo={psicologo} />
                        </div>
                    }
                    <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <EducacionUser
                            psicologo={psicologo}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
