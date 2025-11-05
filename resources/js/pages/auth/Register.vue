<script setup lang="ts">
import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController'
import InputError from '@/components/InputError.vue'
import TextLink from '@/components/TextLink.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AuthBase from '@/layouts/AuthLayout.vue'
import { login } from '@/routes'
import { Form, Head } from '@inertiajs/vue3'
import { LoaderCircle } from 'lucide-vue-next'
</script>

<template>
  <AuthBase title="Create an account" description="Enter your details below to create your account">
    <Head title="Register" />

    <Form
      v-bind="RegisteredUserController.store.form()"
      :reset-on-success="['password', 'password_confirmation']"
      v-slot="{ errors, processing }"
      class="flex flex-col gap-6"
    >
      <div class="grid gap-6">
        <!-- Last Name -->
        <div class="grid gap-2">
          <Label for="last_name">Last Name</Label>
          <Input
            id="last_name"
            type="text"
            required
            name="last_name"
            placeholder="Dela Cruz"
            autocomplete="family-name"
          />
          <InputError :message="errors.last_name" />
        </div>

        <!-- First Name -->
        <div class="grid gap-2">
          <Label for="first_name">First Name</Label>
          <Input
            id="first_name"
            type="text"
            required
            name="first_name"
            placeholder="Juan"
            autocomplete="given-name"
          />
          <InputError :message="errors.first_name" />
        </div>

        <!-- Middle Initial / Suffix -->
        <div class="grid gap-2">
          <Label for="middle_initial">Middle Initial</Label>
          <Input
            id="middle_initial"
            type="text"
            name="middle_initial"
            placeholder="M."
            autocomplete="additional-name"
          />
          <InputError :message="errors.middle_initial" />
        </div>

        <!-- Birthday -->
        <div class="grid gap-2">
          <Label for="birthday">Birthday</Label>
          <Input id="birthday" type="date" required name="birthday" />
          <InputError :message="errors.birthday" />
        </div>

        <!-- Email -->
        <div class="grid gap-2">
          <Label for="email">Email address</Label>
          <Input id="email" type="email" required autocomplete="email" name="email" placeholder="email@example.com" />
          <InputError :message="errors.email" />
        </div>

        <!-- Year Level -->
        <div class="grid gap-2">
          <Label for="year_level">Year Level</Label>
          <select id="year_level" name="year_level" required class="border rounded px-3 py-2">
            <option value="">Select Year Level</option>
            <option value="1st Year">1st Year</option>
            <option value="2nd Year">2nd Year</option>
            <option value="3rd Year">3rd Year</option>
            <option value="4th Year">4th Year</option>
          </select>
          <InputError :message="errors.year_level" />
        </div>

        <!-- Course -->
        <div class="grid gap-2">
          <Label for="course">Course</Label>
          <Input id="course" type="text" required name="course" placeholder="BS Computer Science" />
          <InputError :message="errors.course" />
        </div>

        <!-- Address -->
        <div class="grid gap-2">
          <Label for="address">Address</Label>
          <Input id="address" type="text" required name="address" placeholder="Sorsogon City" />
          <InputError :message="errors.address" />
        </div>

        <!-- Phone -->
        <div class="grid gap-2">
          <Label for="phone">Phone Number</Label>
          <Input id="phone" type="text" required name="phone" placeholder="09171234567" />
          <InputError :message="errors.phone" />
        </div>

        <!-- Password -->
        <div class="grid gap-2">
          <Label for="password">Password</Label>
          <Input id="password" type="password" required autocomplete="new-password" name="password" placeholder="Password" />
          <InputError :message="errors.password" />
        </div>

        <!-- Confirm Password -->
        <div class="grid gap-2">
          <Label for="password_confirmation">Confirm Password</Label>
          <Input id="password_confirmation" type="password" required autocomplete="new-password" name="password_confirmation" placeholder="Confirm password" />
          <InputError :message="errors.password_confirmation" />
        </div>

        <!-- Submit -->
        <Button type="submit" class="mt-2 w-full" :disabled="processing">
          <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />
          Create account
        </Button>
      </div>

      <div class="text-center text-sm text-muted-foreground">
        Already have an account?
        <TextLink :href="login()" class="underline underline-offset-4">Log in</TextLink>
      </div>
    </Form>
  </AuthBase>
</template>
