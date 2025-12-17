<!-- /resources/js/pages/projects/Create.vue -->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Stepper, StepperDescription, StepperItem, StepperTitle, StepperTrigger } from '@/components/ui/stepper';
import WizardDebugPanel from '@/components/WizardDebugPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { BookOpen, Check, ChevronsUpDown, FileText, GraduationCap, School } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import * as z from 'zod';

// Props
interface ProjectCategory {
    id: number;
    slug: string;
    name: string;
    description: string;
    academic_levels: string[];
    default_chapter_count: number;
    target_word_count: number;
    target_duration: string;
}

// Step-aware data types
interface WizardData {
    format_version: string;
    steps: Record<
        string,
        {
            data: Record<string, any>;
            completed: boolean;
            timestamp: string | null;
        }
    >;
    current_step: number;
    furthest_completed_step: number;
}

interface ResumeProject {
    id: number;
    current_step: number;
    setup_step?: number;
    wizard_data: WizardData;
}

interface Props {
    projectCategories: Record<string, ProjectCategory[]>;
    resumeProject?: ResumeProject | null;
}

const props = defineProps<Props>();

// Define the form schemas for each step
const formSchemas = [
    // Step 1: Academic Level & Project Type
    z.object({
        projectType: z.enum(['undergraduate', 'postgraduate']),
        projectCategoryId: z.number({ required_error: 'Please select a project category' }),
    }),

    // Step 2: University Details
    z.object({
        universityId: z.number({ required_error: 'Please select your university' }),
        facultyId: z.number({ required_error: 'Please select your faculty' }),
        departmentId: z.number({ required_error: 'Please select your department' }),
        course: z.string().min(2, 'Course of study is required'),
    }),

    // Step 3: Research & Supervisor Details
    z.object({
        fieldOfStudy: z.string().optional(), // Optional - AI can help guide students
        supervisorName: z.string().optional(),
        matricNumber: z.string().optional(),
        academicSession: z.string().min(1, 'Academic session is required'),
        degree: z.string().min(2, 'Degree is required'),
        degreeAbbreviation: z.string().min(2, 'Degree abbreviation is required'),
        workingMode: z.enum(['auto', 'manual']),
        aiAssistanceLevel: z.enum(['minimal', 'moderate', 'maximum']).optional(),
    }),
];

// Computed schema that reacts to step changes
const currentSchema = computed(() => formSchemas[currentStep.value - 1]);

// Check if in development mode
const isDevelopment = import.meta.env.DEV;

// ==========================================
// STEP-AWARE STATE MANAGEMENT SYSTEM
// ==========================================
const currentStep = ref(1);
const currentProjectId = ref<number | null>(null);
const isInitializing = ref(true);

// Step-aware wizard data
const wizardData = ref<WizardData>({
    format_version: '2.0',
    steps: {},
    current_step: 1,
    furthest_completed_step: 0,
});

// Selected project type for category filtering
const selectedProjectType = ref<string>('');

// Auto-save timer
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;

// API Data - Universities, Faculties, and Departments
const universities = ref<Array<{ id: number; name: string; short_name: string; slug: string; type: string; location: string; state: string }>>([]);
const faculties = ref<Array<{ id: number; name: string; slug: string; description: string; faculty_structure_id: number | null }>>([]);
const departments = ref<Array<{ id: number; faculty_id: number; name: string; slug: string; code: string; description: string }>>([]);
const isLoadingUniversities = ref(false);
const isLoadingFaculties = ref(false);
const isLoadingDepartments = ref(false);
const selectedFacultyId = ref<number | null>(null);
const lastAppliedDegreeSuggestion = ref<{ degree: string; degreeAbbreviation: string } | null>(null);

const getDegreeSuggestion = (
    academicLevel: string | undefined,
    facultyId: number | undefined,
    departmentId: number | undefined,
): { degree: string; degreeAbbreviation: string } => {
    const normalize = (value: unknown) => String(value ?? '').toLowerCase();

    const facultyName = faculties.value.find((f) => f.id === facultyId)?.name ?? '';
    const departmentName = departments.value.find((d) => d.id === departmentId)?.name ?? '';
    const context = `${normalize(facultyName)} ${normalize(departmentName)}`;

    const level = normalize(academicLevel);
    const defaultUndergrad = { degree: 'Bachelor of Science', degreeAbbreviation: 'B.Sc.' };
    const defaultPostgrad = { degree: 'Master of Science', degreeAbbreviation: 'M.Sc.' };

    const presets = [
        {
            keywords: ['engineering', 'engineer'],
            undergraduate: { degree: 'Bachelor of Engineering', degreeAbbreviation: 'B.Eng.' },
            postgraduate: { degree: 'Master of Engineering', degreeAbbreviation: 'M.Eng.' },
        },
        {
            keywords: ['technology', 'polytechnic', 'technological'],
            undergraduate: { degree: 'Bachelor of Technology', degreeAbbreviation: 'B.Tech.' },
            postgraduate: { degree: 'Master of Technology', degreeAbbreviation: 'M.Tech.' },
        },
        {
            keywords: ['management', 'business', 'administration', 'accounting', 'finance', 'marketing', 'economics'],
            undergraduate: defaultUndergrad,
            postgraduate: { degree: 'Master of Business Administration', degreeAbbreviation: 'MBA' },
        },
        {
            keywords: ['education'],
            undergraduate: { degree: 'Bachelor of Education', degreeAbbreviation: 'B.Ed.' },
            postgraduate: { degree: 'Master of Education', degreeAbbreviation: 'M.Ed.' },
        },
        {
            keywords: ['law', 'legal', 'jurisprud'],
            undergraduate: { degree: 'Bachelor of Laws', degreeAbbreviation: 'LL.B.' },
            postgraduate: { degree: 'Master of Laws', degreeAbbreviation: 'LL.M.' },
        },
        {
            keywords: ['arts', 'humanities', 'history', 'linguistics', 'language', 'philosophy', 'literature'],
            undergraduate: { degree: 'Bachelor of Arts', degreeAbbreviation: 'B.A.' },
            postgraduate: { degree: 'Master of Arts', degreeAbbreviation: 'M.A.' },
        },
        {
            keywords: ['agric', 'agriculture', 'forestry'],
            undergraduate: { degree: 'Bachelor of Agriculture', degreeAbbreviation: 'B.Agric.' },
            postgraduate: { degree: 'Master of Agriculture', degreeAbbreviation: 'M.Agric.' },
        },
        {
            keywords: ['medicine', 'medical', 'health', 'nursing', 'pharmacy', 'dentistry'],
            undergraduate: { degree: 'Bachelor of Medicine, Bachelor of Surgery', degreeAbbreviation: 'MBBS' },
            postgraduate: defaultPostgrad,
        },
    ];

    const isPostgraduate = level === 'postgraduate';
    for (const preset of presets) {
        if (preset.keywords.some((keyword) => context.includes(keyword))) {
            return isPostgraduate ? preset.postgraduate : preset.undergraduate;
        }
    }

    return isPostgraduate ? defaultPostgrad : defaultUndergrad;
};

// Popover open states
const universityPopoverOpen = ref(false);
const facultyPopoverOpen = ref(false);
const departmentPopoverOpen = ref(false);

/**
 * GET CURRENT STEP DATA FROM WIZARD
 */
const getCurrentStepData = (step: number): Record<string, any> => {
    const stepKey = step.toString();
    return wizardData.value.steps[stepKey]?.data ?? {};
};

/**
 * SET CURRENT STEP DATA IN WIZARD
 */
const setCurrentStepData = (step: number, data: Record<string, any>) => {
    const stepKey = step.toString();
    wizardData.value.steps[stepKey] = {
        data,
        completed: isStepComplete(step, data),
        timestamp: new Date().toISOString(),
    };

    // Update progress tracking
    wizardData.value.current_step = Math.max(wizardData.value.current_step, step);
    if (wizardData.value.steps[stepKey].completed) {
        wizardData.value.furthest_completed_step = Math.max(wizardData.value.furthest_completed_step, step);
    }
};

/**
 * CHECK IF STEP DATA IS COMPLETE
 */
const isStepComplete = (step: number, data: Record<string, any>): boolean => {
    const requiredFields = {
        1: ['projectType', 'projectCategoryId'],
        2: ['universityId', 'facultyId', 'departmentId', 'course'],
        3: ['academicSession', 'degreeAbbreviation', 'workingMode'], // fieldOfStudy is now optional
    };

    const required = requiredFields[step as keyof typeof requiredFields] || [];
    return required.every((field) => data[field] && data[field] !== '');
};

/**
 * GET ALL STEPS DATA COMBINED
 */
const getAllStepsData = (): Record<string, any> => {
    const allData: Record<string, any> = {};

    // Merge data from all completed steps
    Object.values(wizardData.value.steps).forEach((step) => {
        Object.assign(allData, step.data);
    });

    return allData;
};

/**
 * WATCH FOR STEP CHANGES
 */
watch(currentStep, (newStep) => {
    console.log('👀 Step changed to:', newStep);

    // Update wizard data current step
    wizardData.value.current_step = newStep;

    // Force form re-render with current step data
    nextTick(() => {
        const stepData = getCurrentStepData(newStep);
        const allData = getAllStepsData();

        if (newStep === 3) {
            const degreeDefaults = getDegreeSuggestion(allData.projectType, allData.facultyId, allData.departmentId);

            const normalize = (value: unknown) => String(value ?? '').trim();
            const currentDegree = normalize(stepData.degree);
            const currentAbbr = normalize(stepData.degreeAbbreviation);
            if (
                ! lastAppliedDegreeSuggestion.value &&
                currentDegree === degreeDefaults.degree &&
                currentAbbr === degreeDefaults.degreeAbbreviation
            ) {
                lastAppliedDegreeSuggestion.value = degreeDefaults;
            }
            const matchesLastApplied =
                lastAppliedDegreeSuggestion.value &&
                currentDegree === lastAppliedDegreeSuggestion.value.degree &&
                currentAbbr === lastAppliedDegreeSuggestion.value.degreeAbbreviation;

            const genericUndergrad =
                (currentDegree === 'Bachelor of Science' && currentAbbr === 'B.Sc.') ||
                currentDegree === '' ||
                currentAbbr === '';

            const genericPostgrad =
                (currentDegree === 'Master of Science' && currentAbbr === 'M.Sc.') ||
                currentDegree === '' ||
                currentAbbr === '';

            const isPostgraduate = String(allData.projectType ?? '') === 'postgraduate';
            const isMismatch =
                (isPostgraduate && currentAbbr === 'B.Sc.') || (!isPostgraduate && (currentAbbr === 'M.Sc.' || currentAbbr === 'M.Eng.' || currentAbbr === 'MBA'));

            const shouldApplySuggestion = isMismatch || matchesLastApplied || (isPostgraduate ? genericPostgrad : genericUndergrad);

            const mergedStepData = shouldApplySuggestion
                ? {
                      ...stepData,
                      ...degreeDefaults,
                  }
                : stepData;

            if (JSON.stringify(mergedStepData) !== JSON.stringify(stepData)) {
                setCurrentStepData(newStep, mergedStepData);
                lastAppliedDegreeSuggestion.value = degreeDefaults;
            }

            initialFormValues.value = mergedStepData;
        } else {
            initialFormValues.value = stepData;
        }
        formKey.value++;

        // Sync selectedProjectType for category filtering
        if (stepData.projectType) {
            selectedProjectType.value = stepData.projectType;
        }

        // Load departments if navigating to step 2 with existing faculty selection
        if (newStep === 2 && stepData.facultyId) {
            selectedFacultyId.value = stepData.facultyId;
            fetchDepartmentsByFaculty(stepData.facultyId);
        }
    });
});

/**
 * STEP-AWARE STATE RESTORATION
 */
onMounted(() => {
    // Fetch universities and faculties from API
    fetchUniversities();
    fetchFaculties();

    if (props.resumeProject) {
        console.log('📄 Resuming project setup...', props.resumeProject);

        // Restore wizard data and current step
        currentStep.value = props.resumeProject.current_step;
        currentProjectId.value = props.resumeProject.id;
        wizardData.value = props.resumeProject.wizard_data;

        // Initialize form with current step data
        const currentStepData = getCurrentStepData(currentStep.value);
        initialFormValues.value = currentStepData;

        // Sync selectedProjectType for category filtering
        if (currentStepData.projectType) {
            selectedProjectType.value = currentStepData.projectType;
        }

        // Load departments if faculty is already selected (for step 2 restoration)
        if (currentStepData.facultyId) {
            selectedFacultyId.value = currentStepData.facultyId;
            fetchDepartmentsByFaculty(currentStepData.facultyId);
        }

        // Force form re-render
        formKey.value = Date.now();

        console.log('✅ Step-aware state restored:', {
            step: currentStep.value,
            wizardData: wizardData.value,
            currentStepData,
            selectedProjectType: selectedProjectType.value,
        });
    } else {
        console.log('🆕 Starting fresh project setup');
        initialFormValues.value = {};
        formKey.value = Date.now();
    }

    // Mark initialization complete
    nextTick(() => {
        isInitializing.value = false;
    });
});

/**
 * AUTO-SAVE SYSTEM WITH PROPER CHANGE DETECTION
 */

// Fetch universities from API
const fetchUniversities = async () => {
    isLoadingUniversities.value = true;
    try {
        const response = await fetch('/api/universities');
        const data = await response.json();
        universities.value = data.universities;
    } catch (error) {
        console.error('Failed to fetch universities:', error);
        toast('Error loading universities');
    } finally {
        isLoadingUniversities.value = false;
    }
};

// Fetch faculties from API
const fetchFaculties = async () => {
    isLoadingFaculties.value = true;
    try {
        const response = await fetch('/api/faculties');
        const data = await response.json();
        faculties.value = data.faculties;
    } catch (error) {
        console.error('Failed to fetch faculties:', error);
        toast('Error loading faculties');
    } finally {
        isLoadingFaculties.value = false;
    }
};

// Fetch departments by faculty
const fetchDepartmentsByFaculty = async (facultyId: number) => {
    isLoadingDepartments.value = true;
    try {
        const response = await fetch(`/api/faculties/${facultyId}/departments`);
        const data = await response.json();
        departments.value = data.departments;
    } catch (error) {
        console.error('Failed to fetch departments:', error);
        toast('Error loading departments');
    } finally {
        isLoadingDepartments.value = false;
    }
};

// Watch for faculty changes to load departments
watch(selectedFacultyId, (newFacultyId) => {
    if (newFacultyId) {
        fetchDepartmentsByFaculty(newFacultyId);
    } else {
        departments.value = [];
    }
});
const saveProgress = async (step: number, formData: Record<string, any>) => {
    try {
        console.log('💾 Saving progress - Step:', step, 'Data:', formData);

        // Don't save during initialization
        if (isInitializing.value) {
            console.log('⏸️ Skipping save during initialization');
            return;
        }

        // Get CSRF token more reliably
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (!csrfToken) {
            console.error('❌ CSRF token not found');
            toast('Error', {
                description: 'Security token missing. Please refresh the page.',
            });
            return;
        }

        const response = await fetch(route('projects.save-wizard-progress'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                project_id: currentProjectId.value,
                step: step,
                data: formData,
            }),
        });

        // Handle response
        if (response.ok) {
            const result = await response.json();
            if (result.success) {
                currentProjectId.value = result.project_id;
                lastSavedValues.value = { ...formData };
                console.log('✅ Progress saved successfully');
            } else {
                console.error('❌ Save failed:', result);
                toast('Save Failed', {
                    description: result.message || 'Failed to save progress.',
                });
            }
        } else {
            console.error('❌ Save request failed:', response.status, response.statusText);

            // Handle specific error codes
            if (response.status === 419) {
                toast('Session Expired', {
                    description: 'Please refresh the page to continue.',
                });
                return;
            } else if (response.status === 422) {
                // Validation errors
                const errorData = await response.json().catch(() => ({}));
                console.error('❌ Validation errors:', errorData);
                toast('Validation Error', {
                    description: 'Some form data is invalid.',
                });
                return;
            }

            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
    } catch (error) {
        console.error('❌ Failed to save progress:', error);

        // Show user-friendly error message
        if (error instanceof SyntaxError && error.message.includes('Unexpected token')) {
            toast('Session Error', {
                description: 'Your session may have expired. Please refresh the page.',
            });
        } else {
            toast('Auto-save Error', {
                description: 'Failed to save your progress automatically.',
            });
        }
    }
};

/**
 * IMPROVED DEBOUNCED AUTO-SAVE
 */
const debouncedSave = (step: number, data: Record<string, any>) => {
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
    }

    autoSaveTimer = setTimeout(() => {
        saveProgress(step, data);
    }, 1000); // Save 1 second after user stops typing
};

/**
 * ENHANCED STEP NAVIGATION WITH COMPLETE DATA PRESERVATION
 */
const goToStep = async (targetStep: number, currentValues: Record<string, any>) => {
    // Don't navigate to the same step
    if (targetStep === currentStep.value) return;

    console.log('📄 Navigating from step', currentStep.value, 'to step', targetStep);

    // Save current step data before navigation
    const currentStepData = getCurrentStepFields(currentValues, currentStep.value);
    if (Object.keys(currentStepData).length > 0) {
        console.log('💾 Saving step', currentStep.value, 'data before navigation:', currentStepData);

        // Update wizard data
        setCurrentStepData(currentStep.value, currentStepData);

        // Save to backend
        await saveProgress(currentStep.value, currentStepData);
    }

    // Navigate to target step (this will trigger the watcher)
    currentStep.value = targetStep;

    console.log('✅ Navigation complete - now on step:', currentStep.value);
};

/**
 * CHECK IF STEP IS ACCESSIBLE
 * Users can navigate to any previous step or the next step if current is valid
 */
const isStepAccessible = computed(() => (targetStep: number, currentStep: number, isCurrentValid: boolean) => {
    // Always allow going backward to previously visited steps
    const maxVisited = Math.max(currentStep, props.resumeProject?.setup_step ?? 1);
    if (targetStep <= maxVisited) return true;

    // Allow going forward only if current step is valid and it's the immediate next step
    if (targetStep === currentStep + 1 && isCurrentValid) return true;

    // Block skipping ahead beyond the furthest visited step
    return false;
});

const steps = [
    {
        step: 1,
        title: 'Academic Setup',
        description: 'Level & project type',
        icon: GraduationCap,
    },
    {
        step: 2,
        title: 'Institution',
        description: 'University details',
        icon: School,
    },
    {
        step: 3,
        title: 'Research Details',
        description: 'Field & supervisor info',
        icon: FileText,
    },
];



// Watch for academic level selection to show relevant categories
const getAvailableCategories = (academicLevel: string) => {
    return props.projectCategories[academicLevel] || [];
};

// Watch for project type changes with proper handling
watch(selectedProjectType, (newValue) => {
    if (newValue && !isInitializing.value) {
        const mergedData = {
            ...lastSavedValues.value,
            projectType: newValue,
        };
        debouncedSave(currentStep.value, mergedData);
    }
});

// Track current form values and last saved values
const currentFormValues = ref<Record<string, any>>({});
const lastSavedValues = ref<Record<string, any>>({});
const initialFormValues = ref<Record<string, any>>({});
const formKey = ref(0); // Key for forcing form re-render

// Watch for form values changes to keep selectedProjectType in sync
watch(
    initialFormValues,
    (newValues) => {
        if (newValues?.projectType && newValues.projectType !== selectedProjectType.value) {
            selectedProjectType.value = newValues.projectType;
            console.log('🔄 Synced selectedProjectType from form values:', newValues.projectType);
        }
    },
    { deep: true, immediate: true },
);

/**
 * GET CURRENT STEP FIELDS - Only fields relevant to current step
 */
const getCurrentStepFields = (values: Record<string, any>, step: number): Record<string, any> => {
    const stepFieldsMap = {
        1: ['projectType', 'projectCategoryId'], // Step 1: Academic Level & Project Type
        2: ['universityId', 'facultyId', 'departmentId', 'course'], // Step 2: University Details
        3: [
            'fieldOfStudy',
            'supervisorName',
            'matricNumber',
            'academicSession',
            'degree',
            'degreeAbbreviation',
            'workingMode',
            'aiAssistanceLevel',
        ], // Step 3: Research Details
    };

    const relevantFields = stepFieldsMap[step as keyof typeof stepFieldsMap] || [];

    return Object.fromEntries(
        Object.entries(values || {}).filter(([key, value]) => {
            return relevantFields.includes(key) && value !== null && value !== undefined && value !== '';
        }),
    );
};

/**
 * STEP-AWARE AUTO-SAVE SYSTEM
 */
const autoSaveFormValues = (values: Record<string, any>) => {
    // Don't save during initialization
    if (isInitializing.value) {
        return '';
    }

    // Get only fields relevant to current step
    const stepData = getCurrentStepFields(values, currentStep.value);

    // Update wizard data with current step changes
    if (Object.keys(stepData).length > 0) {
        const previousStepData = getCurrentStepData(currentStep.value);
        const hasChanged = JSON.stringify(stepData) !== JSON.stringify(previousStepData);

        if (hasChanged) {
            console.log('🔄 Step', currentStep.value, 'data changed:', stepData);

            // Update wizard data
            setCurrentStepData(currentStep.value, stepData);

            // Sync selectedProjectType for category filtering
            if (stepData.projectType) {
                selectedProjectType.value = stepData.projectType;
            }

            // Auto-save to backend
            debouncedSave(currentStep.value, stepData);
        }
    }

    return ''; // Return empty string for template
};

function onSubmit(values: any) {
    console.log('🚀 Submitting final step with current values:', values);

    // First save current step data
    const currentStepData = getCurrentStepFields(values, currentStep.value);
    setCurrentStepData(currentStep.value, currentStepData);

    // Get all data from all steps
    const allStepsData = getAllStepsData();
    console.log('📋 All steps data for submission:', allStepsData);

    // Validate we have all required data
    if (!allStepsData.projectType || !allStepsData.universityId || !allStepsData.facultyId || !allStepsData.departmentId) {
        toast('Incomplete Data', {
            description: 'Please complete all previous steps before submitting.',
        });
        return;
    }

    // Prepare project data for final submission
    const projectData = {
        project_category_id: allStepsData.projectCategoryId,
        type: allStepsData.projectType,
        university_id: allStepsData.universityId,
        faculty_id: allStepsData.facultyId,
        department_id: allStepsData.departmentId,
        course: allStepsData.course,
        field_of_study: allStepsData.fieldOfStudy,
        supervisor_name: allStepsData.supervisorName,
        matric_number: allStepsData.matricNumber,
        academic_session: allStepsData.academicSession,
        degree: allStepsData.degree,
        degree_abbreviation: allStepsData.degreeAbbreviation,
        mode: allStepsData.workingMode,
        ai_assistance_level: allStepsData.aiAssistanceLevel,
    };

    console.log('🎯 Final project data for submission:', projectData);

    // Submit to backend
    router.post(route('projects.store'), projectData, {
        onSuccess: () => {
            toast('Project Created!', {
                description: 'Your project has been set up successfully.',
            });
        },
        onError: (errors) => {
            console.error('❌ Project creation failed:', errors);
            toast('Error', {
                description: 'Failed to create project. Please try again.',
            });
        },
    });
}
</script>

<template>
    <AppLayout title="Create New Project">
        <div class="min-h-screen bg-gradient-to-b from-background via-background/95 to-muted/20">
            <div class="mx-auto max-w-4xl space-y-6 p-6 pb-20 lg:p-10 animate-in fade-in slide-in-from-bottom-4 duration-700">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-br from-foreground to-foreground/70 bg-clip-text text-transparent">Create Your Project</h1>
                    <p class="text-muted-foreground mt-2 text-lg">Set up your final year project in just a few steps</p>
                    <div v-if="currentStep > 1"
                        class="mt-4 rounded-lg border border-blue-200 bg-blue-50/50 p-4 text-sm text-blue-600 backdrop-blur-sm flex items-start gap-3 shadow-sm">
                        <span class="text-lg">💡</span>
                        <div>
                            <strong>Tip:</strong> You can click on any previous step to go back and make changes. Your
                        progress is automatically saved!
                        </div>
                    </div>
                </div>

            <Card>
                <CardContent class="pt-6">
                    <Form :key="formKey" v-slot="{ meta, values, validate }" as="" keep-values
                        :validation-schema="toTypedSchema(currentSchema)" :initial-values="initialFormValues">
                        <!-- Auto-save values when they change -->
                        <template v-if="!isInitializing">{{ autoSaveFormValues(values) }}</template>
                        <Stepper v-model="currentStep" class="w-full">
                            <form @submit="
                                (e) => {
                                    e.preventDefault();
                                    validate();

                                    if (currentStep === steps.length && meta.valid) {
                                        onSubmit(values);
                                    }
                                }
                            " class="w-full">
                                <!-- Stepper Header with Fixed Line Positioning -->
                                <div class="flex w-full flex-col items-start justify-start gap-4 md:flex-row md:gap-0">
                                    <!-- Stepper Items -->
                                    <template v-for="(step, index) in steps" :key="step.step">
                                        <StepperItem v-slot="{ state }"
                                            class="relative flex flex-shrink-0 flex-row items-center gap-4 md:flex-col md:gap-0"
                                            :step="step.step">
                                            <StepperTrigger as-child>
                                                <Button
                                                    :variant="state === 'completed' || state === 'active' ? 'default' : 'outline'"
                                                    size="icon" class="relative z-10 shrink-0 rounded-full" :class="[
                                                        state === 'active' && 'ring-2 ring-ring ring-offset-2 ring-offset-background',
                                                        !isStepAccessible(step.step, currentStep, meta.valid) && 'cursor-not-allowed opacity-50',
                                                    ]"
                                                    :disabled="!isStepAccessible(step.step, currentStep, meta.valid)"
                                                    @click="goToStep(step.step, values)" type="button">
                                                    <Check v-if="state === 'completed'" class="size-5" />
                                                    <component :is="step.icon" v-else class="size-5" />
                                                </Button>
                                            </StepperTrigger>

                                            <div
                                                class="flex flex-col items-start text-left md:mt-5 md:items-center md:text-center">
                                                <StepperTitle :class="[
                                                    state === 'active' && 'text-primary',
                                                    isStepAccessible(step.step, currentStep, meta.valid) &&
                                                    step.step !== currentStep &&
                                                    'cursor-pointer text-blue-600 hover:text-blue-800',
                                                ]" class="text-sm font-semibold transition lg:text-base"
                                                    @click="isStepAccessible(step.step, currentStep, meta.valid) ? goToStep(step.step, values) : null">
                                                    {{ step.title }}
                                                </StepperTitle>
                                                <StepperDescription :class="[
                                                    state === 'active' && 'text-primary',
                                                    isStepAccessible(step.step, currentStep, meta.valid) &&
                                                    step.step !== currentStep &&
                                                    'text-blue-500',
                                                ]" class="text-xs text-muted-foreground transition md:text-sm">
                                                    {{ step.description }}
                                                    <span
                                                        v-if="isStepAccessible(step.step, currentStep, meta.valid) && step.step < currentStep"
                                                        class="mt-1 block text-xs text-blue-600">
                                                        (click to edit)
                                                    </span>
                                                </StepperDescription>
                                            </div>
                                        </StepperItem>

                                        <!-- Connector line between steps (Desktop only) -->
                                        <div v-if="index < steps.length - 1" :class="[
                                            'mt-5 hidden h-0.5 w-full max-w-[200px] transition-all duration-300 md:block',
                                            currentStep > step.step ? 'bg-primary' : 'bg-muted',
                                        ]" />
                                    </template>
                                </div>

                                <!-- Form Content -->
                                <div class="mt-8 space-y-6">
                                    <!-- Step 1: Academic Level & Project Type -->
                                    <div v-if="currentStep === 1"
                                        class="space-y-6 animate-in fade-in slide-in-from-right-4 duration-500">
                                        <FormField v-slot="{ componentField }" name="projectType">
                                            <FormItem>
                                                <FormLabel>What is your academic level?</FormLabel>
                                                <FormControl>
                                                    <RadioGroup :model-value="componentField.modelValue"
                                                        @update:model-value="
                                                            (value: string) => {
                                                                componentField['onUpdate:modelValue']?.(value);
                                                                selectedProjectType = value;
                                                            }
                                                        ">
                                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                            <label
                                                                class="flex cursor-pointer items-center space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent">
                                                                <RadioGroupItem value="undergraduate" />
                                                                <div class="space-y-1">
                                                                    <p class="text-sm font-medium">Undergraduate</p>
                                                                    <p class="text-xs text-muted-foreground">BSc/BTech
                                                                        final year project</p>
                                                                </div>
                                                            </label>
                                                            <label
                                                                class="flex cursor-pointer items-center space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent">
                                                                <RadioGroupItem value="postgraduate" />
                                                                <div class="space-y-1">
                                                                    <p class="text-sm font-medium">Postgraduate</p>
                                                                    <p class="text-xs text-muted-foreground">MSc/PhD
                                                                        thesis</p>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </RadioGroup>
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>

                                        <!-- Project Category -->
                                        <FormField v-slot="{ componentField }" name="projectCategoryId">
                                            <FormItem>
                                                <FormLabel>What type of project are you creating?</FormLabel>
                                                <FormControl>
                                                    <RadioGroup :model-value="componentField.modelValue?.toString()"
                                                        @update:model-value="
                                                            (value: string) => componentField['onUpdate:modelValue']?.(parseInt(value))
                                                        ">
                                                        <div class="space-y-3">
                                                            <template
                                                                v-for="category in getAvailableCategories(selectedProjectType)"
                                                                :key="category.id">
                                                                <label
                                                                    class="flex cursor-pointer items-start space-y-0 space-x-3 rounded-md border p-4 transition-all duration-200 hover:border-primary hover:bg-accent/50 has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5">
                                                                    <RadioGroupItem :value="category.id.toString()"
                                                                        class="mt-1" />
                                                                    <div class="flex-1 space-y-2">
                                                                        <div>
                                                                            <p class="font-medium">{{ category.name }}
                                                                            </p>
                                                                            <p class="text-sm text-muted-foreground">{{
                                                                                category.description }}</p>
                                                                        </div>
                                                                        <div
                                                                            class="grid grid-cols-1 gap-2 text-xs text-muted-foreground sm:grid-cols-3">
                                                                            <div class="flex items-center gap-1">
                                                                                <BookOpen class="h-3 w-3" />
                                                                                {{ category.default_chapter_count }}
                                                                                chapters
                                                                            </div>
                                                                            <div class="flex items-center gap-1">
                                                                                <FileText class="h-3 w-3" />
                                                                                ~{{ (category.target_word_count /
                                                                                    1000).toFixed(0) }}k words
                                                                            </div>
                                                                            <div class="flex items-center gap-1">
                                                                                <GraduationCap class="h-3 w-3" />
                                                                                {{ category.target_duration }}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </label>
                                                            </template>
                                                            <div v-if="!selectedProjectType"
                                                                class="py-8 text-center text-muted-foreground">
                                                                Please select an academic level first
                                                            </div>
                                                            <div v-else-if="getAvailableCategories(selectedProjectType).length === 0"
                                                                class="py-8 text-center text-muted-foreground">
                                                                No project categories available for this academic level
                                                            </div>
                                                        </div>
                                                    </RadioGroup>
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>
                                    </div>

                                    <!-- Step 2: University Details -->
                                    <div v-if="currentStep === 2"
                                        class="space-y-4 animate-in fade-in slide-in-from-right-4 duration-500">
                                        <FormField v-slot="{ componentField }" name="universityId">
                                            <FormItem class="flex flex-col">
                                                <FormLabel>University</FormLabel>
                                                <Popover v-model:open="universityPopoverOpen">
                                                    <PopoverTrigger as-child>
                                                        <FormControl>
                                                            <Button variant="outline" role="combobox" :class="cn(
                                                                'w-full justify-between',
                                                                !componentField.modelValue && 'text-muted-foreground',
                                                            )
                                                                ">
                                                                {{
                                                                    componentField.modelValue
                                                                        ? universities.find(
                                                                            (university) => university.id ===
                                                                                componentField.modelValue,
                                                                        )?.name
                                                                        : 'Select your university...'
                                                                }}
                                                                <ChevronsUpDown
                                                                    class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                            </Button>
                                                        </FormControl>
                                                    </PopoverTrigger>
                                                    <PopoverContent class="w-full p-0" align="start">
                                                        <Command>
                                                            <CommandInput placeholder="Search university..." />
                                                            <CommandEmpty>No university found.</CommandEmpty>
                                                            <CommandList>
                                                                <CommandGroup>
                                                                    <CommandItem v-for="university in universities"
                                                                        :key="university.id"
                                                                        :value="university.name" @select="
                                                                            () => {
                                                                                componentField['onUpdate:modelValue']?.(university.id);
                                                                                universityPopoverOpen = false;
                                                                            }
                                                                        ">
                                                                        {{ university.name }}
                                                                        <Check :class="cn(
                                                                            'ml-auto h-4 w-4',
                                                                            university.id === componentField.modelValue
                                                                                ? 'opacity-100'
                                                                                : 'opacity-0',
                                                                        )
                                                                            " />
                                                                    </CommandItem>
                                                                </CommandGroup>
                                                            </CommandList>
                                                        </Command>
                                                    </PopoverContent>
                                                </Popover>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <FormField v-slot="{ componentField }" name="facultyId">
                                                <FormItem class="flex flex-col">
                                                    <FormLabel>Faculty</FormLabel>
                                                    <Popover v-model:open="facultyPopoverOpen">
                                                        <PopoverTrigger as-child>
                                                            <FormControl>
                                                                <Button variant="outline" role="combobox" :class="cn(
                                                                    'w-full justify-between',
                                                                    !componentField.modelValue && 'text-muted-foreground',
                                                                )
                                                                    ">
                                                                    {{
                                                                        componentField.modelValue
                                                                            ? 'Faculty of ' + faculties.find((faculty) => faculty.id ===
                                                                                componentField.modelValue)
                                                                                ?.name
                                                                            : 'Select faculty...'
                                                                    }}
                                                                    <ChevronsUpDown
                                                                        class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                                </Button>
                                                            </FormControl>
                                                        </PopoverTrigger>
                                                        <PopoverContent class="w-[300px] p-0">
                                                            <Command>
                                                                <CommandInput placeholder="Search faculty..." />
                                                                <CommandEmpty>No faculty found.</CommandEmpty>
                                                                <CommandList>
                                                                    <CommandGroup>
                                                                        <CommandItem v-for="faculty in faculties"
                                                                            :key="faculty.id" :value="faculty.id"
                                                                            @select="
                                                                                () => {
                                                                                    componentField['onUpdate:modelValue']?.(faculty.id);
                                                                                    selectedFacultyId = faculty.id;
                                                                                    facultyPopoverOpen = false;
                                                                                }
                                                                            ">
                                                                            <Check :class="cn(
                                                                                'mr-2 h-4 w-4',
                                                                                componentField.modelValue === faculty.id
                                                                                    ? 'opacity-100'
                                                                                    : 'opacity-0',
                                                                            )
                                                                                " />
                                                                            Faculty of {{ faculty.name }}
                                                                        </CommandItem>
                                                                    </CommandGroup>
                                                                </CommandList>
                                                            </Command>
                                                        </PopoverContent>
                                                    </Popover>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <FormField v-slot="{ componentField }" name="departmentId">
                                                <FormItem class="flex flex-col">
                                                    <FormLabel>Department</FormLabel>
                                                    <Popover v-model:open="departmentPopoverOpen">
                                                        <PopoverTrigger as-child>
                                                            <FormControl>
                                                                <Button variant="outline" role="combobox" :class="cn(
                                                                    'w-full justify-between',
                                                                    !componentField.modelValue && 'text-muted-foreground',
                                                                )
                                                                    ">
                                                                    {{
                                                                        componentField.modelValue
                                                                            ? departments.find((dept) => dept.id ===
                                                                                componentField.modelValue)
                                                                                ?.name
                                                                            : 'Select department...'
                                                                    }}
                                                                    <ChevronsUpDown
                                                                        class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                                </Button>
                                                            </FormControl>
                                                        </PopoverTrigger>
                                                        <PopoverContent class="w-[350px] p-0">
                                                            <Command>
                                                                <CommandInput placeholder="Search department..." />
                                                                <CommandEmpty>No department found.</CommandEmpty>
                                                                <CommandList>
                                                                    <CommandGroup>
                                                                        <CommandItem v-for="dept in departments"
                                                                            :key="dept.id" :value="dept.id"
                                                                            @select="
                                                                                () => {
                                                                                    componentField['onUpdate:modelValue']?.(dept.id);
                                                                                    departmentPopoverOpen = false;
                                                                                }
                                                                            ">
                                                                            <Check :class="cn(
                                                                                'mr-2 h-4 w-4',
                                                                                componentField.modelValue === dept.id
                                                                                    ? 'opacity-100'
                                                                                    : 'opacity-0',
                                                                            )
                                                                                " />
                                                                            {{ dept.name }}
                                                                        </CommandItem>
                                                                    </CommandGroup>
                                                                </CommandList>
                                                            </Command>
                                                        </PopoverContent>
                                                    </Popover>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>
                                        </div>

                                        <FormField v-slot="{ componentField }" name="course">
                                            <FormItem>
                                                <FormLabel>Course of Study</FormLabel>
                                                <FormControl>
                                                    <Input type="text" placeholder="e.g., Computer Science"
                                                        v-bind="componentField" />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>
                                    </div>

                                    <!-- Step 3: Research & Working Details -->
                                    <div v-if="currentStep === 3"
                                        class="space-y-6 animate-in fade-in slide-in-from-right-4 duration-500">
                                        <FormField v-slot="{ componentField }" name="fieldOfStudy">
                                            <FormItem>
                                                <FormLabel>Field/Area of Research (Optional)</FormLabel>
                                                <FormControl>
                                                    <Input type="text"
                                                        placeholder="e.g., Artificial Intelligence, Web Development (leave blank if unsure)"
                                                        v-bind="componentField" />
                                                </FormControl>
                                                <FormDescription>
                                                    Your specific area of focus for this project. Leave blank if you
                                                    need AI guidance in
                                                    choosing a
                                                    research area.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>

                                        <FormField v-slot="{ componentField }" name="supervisorName">
                                            <FormItem>
                                                <FormLabel>Supervisor Name (Optional)</FormLabel>
                                                <FormControl>
                                                    <Input type="text" placeholder="e.g., Dr. John Doe"
                                                        v-bind="componentField" />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <FormField v-slot="{ componentField }" name="matricNumber">
                                                <FormItem>
                                                    <FormLabel>Matric Number (Optional)</FormLabel>
                                                    <FormControl>
                                                        <Input type="text" placeholder="e.g., 2019/1234"
                                                            v-bind="componentField" />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <FormField v-slot="{ componentField }" name="academicSession">
                                                <FormItem>
                                                    <FormLabel>Academic Session</FormLabel>
                                                    <FormControl>
                                                        <Input type="text" placeholder="e.g., 2024/2025"
                                                            v-bind="componentField" />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>
                                        </div>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <FormField v-slot="{ componentField }" name="degree">
                                                <FormItem>
                                                    <FormLabel>Degree</FormLabel>
                                                    <FormControl>
                                                        <Input type="text" placeholder="e.g., Bachelor of Science"
                                                            v-bind="componentField" />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <FormField v-slot="{ componentField }" name="degreeAbbreviation">
                                                <FormItem>
                                                    <FormLabel>Degree Abbreviation</FormLabel>
                                                    <FormControl>
                                                        <Input type="text" placeholder="e.g., B.Sc., M.Sc."
                                                            v-bind="componentField" />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>
                                        </div>

                                        <!-- Working Mode -->
                                        <FormField v-slot="{ componentField }" name="workingMode">
                                            <FormItem>
                                                <FormLabel>How would you like to work on your project?</FormLabel>
                                                <FormControl>
                                                    <RadioGroup v-bind="componentField">
                                                        <div class="space-y-3">
                                                            <label
                                                                class="flex cursor-pointer items-start space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent">
                                                                <RadioGroupItem value="auto" class="mt-1" />
                                                                <div class="space-y-1">
                                                                    <p class="font-medium">Auto Mode</p>
                                                                    <p class="text-sm text-muted-foreground">
                                                                        AI generates complete chapters. You review and
                                                                        approve each section.
                                                                        Perfect
                                                                        for quick completion.
                                                                    </p>
                                                                </div>
                                                            </label>
                                                            <label
                                                                class="flex cursor-pointer items-start space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent">
                                                                <RadioGroupItem value="manual" class="mt-1" />
                                                                <div class="space-y-1">
                                                                    <p class="font-medium">Manual Mode</p>
                                                                    <p class="text-sm text-muted-foreground">
                                                                        Co-write with AI assistance. Get suggestions as
                                                                        you type, maintain full
                                                                        control.
                                                                    </p>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </RadioGroup>
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>
                                    </div>
                                </div>

                                <!-- Navigation Buttons -->
                                <div class="mt-8 flex justify-between">
                                    <Button :disabled="currentStep <= 1" variant="outline"
                                        @click="goToStep(currentStep - 1, values)" type="button">
                                        Back
                                    </Button>

                                    <div class="flex gap-3">
                                        <Button v-if="currentStep !== steps.length" :disabled="!meta.valid"
                                            @click="goToStep(currentStep + 1, values)" type="button">
                                            Next
                                        </Button>

                                        <Button v-if="currentStep === steps.length" type="submit"
                                            :disabled="!meta.valid"> Complete Setup
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        </Stepper>
                    </Form>
                </CardContent>
            </Card>
        </div>

        <!-- Debug Panel (Development Only) -->
        <WizardDebugPanel v-if="isDevelopment" :current-step="currentStep" :project-id="currentProjectId"
            :form-values="currentFormValues" :saved-values="lastSavedValues" :is-initializing="isInitializing"
            @force-save="() => saveProgress(currentStep, currentFormValues)" @reset-form="() => { }" />
        </div>
    </AppLayout>
</template>
