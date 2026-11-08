package com.example.twoactivities;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;

import androidx.appcompat.app.AppCompatActivity;

public class MainActivity extends AppCompatActivity {

    public static final String EXTRA_TEXT_ID =
            "com.example.twoactivities.extra.TEXT_ID";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
    }

    public void openText(View view) {
        int textId;

        if (view.getId() == R.id.button_text_two) {
            textId = 2;
        } else if (view.getId() == R.id.button_text_three) {
            textId = 3;
        } else {
            textId = 1;
        }

        Intent intent = new Intent(this, SecondActivity.class);
        intent.putExtra(EXTRA_TEXT_ID, textId);
        startActivity(intent);
    }
}
