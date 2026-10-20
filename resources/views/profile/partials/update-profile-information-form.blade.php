<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="telephone" :value="__('Telephone')" />
            <x-text-input id="telephone" name="telephone" type="text" class="mt-1 block w-full" :value="old('telephone', $user->telephone)" autocomplete="telephone" />
            <x-input-error class="mt-2" :messages="$errors->get('telephone')" />
        </div>

        <div>
            <input type="hidden" name="has_shooting_leader_license" value="0">
            <label class="inline-flex items-start gap-2">
                <input
                    type="checkbox"
                    name="has_shooting_leader_license"
                    id="profileHasShootingLeaderLicense"
                    value="1"
                    class="mt-1 rounded border-gray-300 text-red-700 shadow-sm focus:ring-red-700"
                    @checked((bool) old('has_shooting_leader_license', $user->has_shooting_leader_license))
                >
                <span class="text-sm text-gray-700">
                    Posiadam uprawnienia prowadzącego strzelanie
                </span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('has_shooting_leader_license')" />
        </div>

        <div id="profileShootingLeaderLicenseNumberField">
            <x-input-label for="profileShootingLeaderLicenseNumber" value="Numer uprawnień / legitymacji" />
            <x-text-input
                id="profileShootingLeaderLicenseNumber"
                name="shooting_leader_license_number"
                type="text"
                maxlength="100"
                class="mt-1 block w-full"
                :value="old('shooting_leader_license_number', $user->shooting_leader_license_number)"
            />
            <x-input-error class="mt-2" :messages="$errors->get('shooting_leader_license_number')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const checkbox = document.getElementById('profileHasShootingLeaderLicense');
            const field = document.getElementById('profileShootingLeaderLicenseNumberField');
            const input = document.getElementById('profileShootingLeaderLicenseNumber');

            function toggleLicenseNumber() {
                const checked = checkbox.checked;
                input.required = checked;
                field.classList.toggle('hidden', !checked);

                if (!checked) {
                    input.value = '';
                }
            }

            checkbox.addEventListener('change', toggleLicenseNumber);
            toggleLicenseNumber();
        });
    </script>
</section>
