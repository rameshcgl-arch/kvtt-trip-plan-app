# Handle the reserved 'in' keyword in package name
-keep class **.kashivaranasi.** { *; }

# Preserve data models for GSON
-keep class in.kashivaranasi.data.models.** { *; }
-keepclassmembers class in.kashivaranasi.data.models.** { *; }

# Keep GSON SerializedName annotations
-keepattributes *Annotation*, Signature, EnclosingMethod, InnerClasses
-keep class com.google.gson.** { *; }

# Retrofit
-keep class retrofit2.** { *; }
-keepclassmembernames class retrofit2.MethodHandler { *** invoke(...); }

# OkHttp
-keep class okhttp3.** { *; }

# Kotlin Coroutines
-keepnames class kotlinx.coroutines.internal.MainDispatcherFactory {}
-keepnames class kotlinx.coroutines.CoroutineExceptionHandler {}
-keepclassmembernames class kotlinx.coroutines.android.HandlerContext {
    private *** handler;
}

# Hilt / Dagger
-keep class dagger.hilt.** { *; }
-keep @dagger.hilt.android.lifecycle.HiltViewModel class *
-keep class * extends androidx.lifecycle.ViewModel

# Firebase
-keep class com.google.firebase.** { *; }

# Prevent shrinking of important resources
-keep class com.google.android.gms.** { *; }
