<!-- resources/js/Pages/StudentFees/Create.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Trash2, ArrowLeft } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Subject {
    id: number;
    code: string;
    name: string;
    units: number;
    price_per_unit: number;
    has_lab: boolean;
    lab_fee: number;
    total_cost: number;
}

interface Fee {
    id: number;
    name: string;
    category: string;
    amount: number;
}

interface SelectedSubject {
    id: number;
    units: number;
    amount: number;
}

interface SelectedFee {
    id: number;
    amount: number;
}

interface Props {
    student?: any;
    subjects: Subject[];
    fees: Fee[];
    yearLevels: string[];
    semesters: string[];
    schoolYears: string[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: 'Create Assessment' },
];

const selectedSubjects = ref<SelectedSubject[]>([]);
const selectedFees = ref<SelectedFee[]>([]);

// Use any to avoid deep instantiation
const form: any = useForm({
    user_id: props.student?.id || null,
    year_level: props.student?.year_level || '',
    semester: '',
    school_year: props.schoolYears[0] || '',
    subjects: [],
    other_fees: [],
});

const tuitionTotal = computed(() => {
    return selectedSubjects.value.reduce((sum, s) => sum + s.amount, 0);
});

const otherFeesTotal = computed(() => {
    return selectedFees.value.reduce((sum, f) => sum + f.amount, 0);
});

const grandTotal = computed(() => {
    return tuitionTotal.value + otherFeesTotal.value;
});

const addSubject = (subject: Subject) => {
    const exists = selectedSubjects.value.find(s => s.id === subject.id);
    if (!exists) {
        selectedSubjects.value.push({
            id: subject.id,
            units: subject.units,
            amount: subject.total_cost,
        });
    }
};

const removeSubject = (subjectId: number) => {
    selectedSubjects.value = selectedSubjects.value.filter(s => s.id !== subjectId);
};

const addFee = (fee: Fee) => {
    const exists = selectedFees.value.find(f => f.id === fee.id);
    if (!exists) {
        selectedFees.value.push({
            id: fee.id,
            amount: fee.amount,
        });
    }
};

const removeFee = (feeId: number) => {
    selectedFees.value = selectedFees.value.filter(f => f.id !== feeId);
};

const getSubjectDetails = (subjectId: number) => {
    return props.subjects.find(s => s.id === subjectId);
};

const getFeeDetails = (feeId: number) => {
    return props.fees.find(f => f.id === feeId);
};

watch([selectedSubjects, selectedFees], () => {
    form.subjects = selectedSubjects.value;
    form.other_fees = selectedFees.value;
}, { deep: true });

const submit = () => {
    form.post(route('student-fees.store'), {
        preserveScroll: true,
    });
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};
</script>

<template>
    <Head title="Create Student Assessment" />

    <AppLayout>
        <div class="space-y-6 max-w-5xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="route('student-fees.index')">
                    <Button variant="outline" size="sm" class="flex items-center gap-2">
                        <ArrowLeft class="w-4 h-4" />
                        Back
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold">Create Student Assessment</h1>
                    <p class="text-gray-600 mt-2">
                        Create fee assessment for student
                    </p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Student Information -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Student Information</h2>
                    <div v-if="student" class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg">
                        <div>
                            <Label class="text-sm text-gray-600">Student ID</Label>
                            <p class="font-medium">{{ student.student_id }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Name</Label>
                            <p class="font-medium">{{ student.name }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Course</Label>
                            <p class="font-medium">{{ student.course }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Year Level</Label>
                            <p class="font-medium">{{ student.year_level }}</p>
                        </div>
                    </div>
                    <div v-else class="text-center py-8">
                        <p class="text-gray-500 mb-4">No student selected. Please select a student from the list.</p>
                        <Link :href="route('student-fees.index')">
                            <Button variant="outline">
                                Go to Student List
                            </Button>
                        </Link>
                    </div>
                </div>

                <!-- Term Information -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Term Information</h2>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="year_level">Year Level</Label>
                            <select
                                id="year_level"
                                v-model="form.year_level"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Select year level</option>
                                <option
                                    v-for="year in yearLevels"
                                    :key="year"
                                    :value="year"
                                >
                                    {{ year }}
                                </option>
                            </select>
                            <p v-if="form.errors?.year_level" class="text-sm text-red-500">
                                {{ form.errors.year_level }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="semester">Semester</Label>
                            <select
                                id="semester"
                                v-model="form.semester"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Select semester</option>
                                <option
                                    v-for="sem in semesters"
                                    :key="sem"
                                    :value="sem"
                                >
                                    {{ sem }}
                                </option>
                            </select>
                            <p v-if="form.errors?.semester" class="text-sm text-red-500">
                                {{ form.errors.semester }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="school_year">School Year</Label>
                            <select
                                id="school_year"
                                v-model="form.school_year"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Select school year</option>
                                <option
                                    v-for="sy in schoolYears"
                                    :key="sy"
                                    :value="sy"
                                >
                                    {{ sy }}
                                </option>
                            </select>
                            <p v-if="form.errors?.school_year" class="text-sm text-red-500">
                                {{ form.errors.school_year }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Subjects -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Subjects</h2>
                    
                    <!-- Available Subjects -->
                    <div class="space-y-2 mb-4">
                        <Label>Available Subjects</Label>
                        <div class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto border rounded-lg p-2">
                            <div
                                v-for="subject in subjects"
                                :key="subject.id"
                                class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer"
                                @click="addSubject(subject)"
                            >
                                <div>
                                    <p class="font-medium">{{ subject.code }} - {{ subject.name }}</p>
                                    <p class="text-sm text-gray-600">
                                        {{ subject.units }} units × {{ formatCurrency(subject.price_per_unit) }}
                                        <span v-if="subject.has_lab">+ Lab Fee {{ formatCurrency(subject.lab_fee) }}</span>
                                    </p>
                                </div>
                                <div class="font-medium">
                                    {{ formatCurrency(subject.total_cost) }}
                                </div>
                            </div>
                            <div v-if="subjects.length === 0" class="text-center py-4 text-gray-500">
                                No subjects available
                            </div>
                        </div>
                    </div>

                    <!-- Selected Subjects -->
                    <div class="space-y-2">
                        <Label>Selected Subjects</Label>
                        <div class="space-y-2">
                            <div
                                v-for="selected in selectedSubjects"
                                :key="selected.id"
                                class="flex items-center justify-between p-3 border rounded-lg"
                            >
                                <div class="flex-1">
                                    <p class="font-medium">
                                        {{ getSubjectDetails(selected.id)?.code }} - 
                                        {{ getSubjectDetails(selected.id)?.name }}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        {{ selected.units }} units
                                    </p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="font-medium">{{ formatCurrency(selected.amount) }}</span>
                                    <button
                                        type="button"
                                        class="text-red-500 hover:text-red-700"
                                        @click="removeSubject(selected.id)"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="selectedSubjects.length === 0" class="text-center py-4 text-gray-500 border rounded-lg">
                                No subjects selected
                            </div>
                        </div>
                        <p v-if="form.errors?.subjects" class="text-sm text-red-500">
                            {{ form.errors.subjects }}
                        </p>
                    </div>

                    <!-- Tuition Total -->
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg mt-4">
                        <span class="font-medium">Total Tuition Fee</span>
                        <span class="text-xl font-bold">{{ formatCurrency(tuitionTotal) }}</span>
                    </div>
                </div>

                <!-- Other Fees -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Other Fees</h2>
                    
                    <!-- Available Fees -->
                    <div class="space-y-2 mb-4">
                        <Label>Available Fees</Label>
                        <div class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto border rounded-lg p-2">
                            <div
                                v-for="fee in fees"
                                :key="fee.id"
                                class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer"
                                @click="addFee(fee)"
                            >
                                <div>
                                    <p class="font-medium">{{ fee.name }}</p>
                                    <p class="text-sm text-gray-600">{{ fee.category }}</p>
                                </div>
                                <div class="font-medium">
                                    {{ formatCurrency(fee.amount) }}
                                </div>
                            </div>
                            <div v-if="fees.length === 0" class="text-center py-4 text-gray-500">
                                No fees available
                            </div>
                        </div>
                    </div>

                    <!-- Selected Fees -->
                    <div class="space-y-2">
                        <Label>Selected Fees</Label>
                        <div class="space-y-2">
                            <div
                                v-for="selected in selectedFees"
                                :key="selected.id"
                                class="flex items-center justify-between p-3 border rounded-lg"
                            >
                                <div class="flex-1">
                                    <p class="font-medium">{{ getFeeDetails(selected.id)?.name }}</p>
                                    <p class="text-sm text-gray-600">
                                        {{ getFeeDetails(selected.id)?.category }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="font-medium">{{ formatCurrency(selected.amount) }}</span>
                                    <button
                                        type="button"
                                        class="text-red-500 hover:text-red-700"
                                        @click="removeFee(selected.id)"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="selectedFees.length === 0" class="text-center py-4 text-gray-500 border rounded-lg">
                                No fees selected
                            </div>
                        </div>
                    </div>

                    <!-- Other Fees Total -->
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg mt-4">
                        <span class="font-medium">Total Other Fees</span>
                        <span class="text-xl font-bold">{{ formatCurrency(otherFeesTotal) }}</span>
                    </div>
                </div>

                <!-- Grand Total -->
                <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-6">
                    <div class="flex justify-between items-center">
                        <span class="text-xl font-bold">Total Assessment Fee Amount</span>
                        <span class="text-3xl font-bold text-blue-600">
                            {{ formatCurrency(grandTotal) }}
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4">
                    <Link :href="route('student-fees.index')">
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </Link>
                    <Button 
                        type="submit" 
                        :disabled="form.processing || !form.user_id || selectedSubjects.length === 0"
                    >
                        Create Assessment
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>